import Alpine from 'alpinejs';
import { connectEcho } from '../shared/echo';

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const json = async (url, opts = {}) => {
  const r = await fetch(url, {
    credentials: 'same-origin',
    ...opts,
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', ...(opts.body && !(opts.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}), ...(opts.headers || {}) },
  });
  const data = r.headers.get('content-type')?.includes('json') ? await r.json() : null;
  if (!r.ok) throw Object.assign(new Error(data?.message || `HTTP ${r.status}`), { status: r.status, data });
  return data;
};
window.doogsanFetch = json;

const slugify = (s) => (s || '').toString().normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
  .replace(/[’'"]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90);

/* Confirm destructive actions declaratively: <form data-confirm="…"> */
document.addEventListener('submit', (e) => {
  const msg = e.target.dataset?.confirm;
  if (msg && !window.confirm(msg)) e.preventDefault();
});

document.addEventListener('alpine:init', () => {
  Alpine.store('toast', {
    items: [],
    push(text, kind = 'ok') { const id = Date.now() + Math.random(); this.items.push({ id, text, kind }); setTimeout(() => (this.items = this.items.filter((t) => t.id !== id)), 4200); },
  });

  /* ── Media picker (global modal) ───────────────────────────── */
  Alpine.store('media', {
    open: false, items: [], q: '', type: 'image', page: 1, last: 1, loading: false, uploading: false, cb: null, multiple: false, selected: [],
    async show(cb, { type = 'image', multiple = false } = {}) {
      Object.assign(this, { cb, type, multiple, selected: [], open: true, page: 1 });
      await this.load();
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const d = await json(`/admin/media/picker?type=${this.type}&q=${encodeURIComponent(this.q)}&page=${page}`);
        this.items = d.data; this.page = d.current_page; this.last = d.last_page;
      } finally { this.loading = false; }
    },
    async upload(files) {
      if (!files?.length) return;
      this.uploading = true;
      const fd = new FormData();
      [...files].forEach((f) => fd.append('files[]', f));
      try {
        const res = await json('/admin/media', { method: 'POST', body: fd });
        Alpine.store('toast').push(`${res.length} file(s) uploaded`);
        await this.load();
        if (!this.multiple && res[0]) this.pick(res[0]);
      } catch (e) { Alpine.store('toast').push(e.message, 'err'); } finally { this.uploading = false; }
    },
    toggle(item) {
      if (!this.multiple) return this.pick(item);
      const i = this.selected.findIndex((s) => s.id === item.id);
      i >= 0 ? this.selected.splice(i, 1) : this.selected.push(item);
    },
    pick(item) { this.cb?.(item); this.open = false; },
    confirmMany() { this.cb?.(this.selected); this.open = false; },
  });

  /* Single media field: <div x-data="mediaField(id, url)"> */
  Alpine.data('mediaField', (id = null, url = null, type = 'image') => ({
    id, url, type,
    choose() { Alpine.store('media').show((m) => { this.id = m.id; this.url = m.url; }, { type: this.type }); },
    clear() { this.id = null; this.url = null; },
  }));

  /* ── Charts (lazy-loaded Chart.js) ─────────────────────────── */
  Alpine.data('chart', (config) => ({
    async init() {
      const { Chart, registerables } = await import('chart.js');
      Chart.register(...registerables);
      Chart.defaults.color = '#9fb5ad';
      Chart.defaults.font.family = 'Manrope Variable, system-ui';
      Chart.defaults.borderColor = '#1b3029';
      new Chart(this.$refs.canvas, config);
    },
  }));

  /* ── 2FA QR code ───────────────────────────────────────────── */
  Alpine.data('qr', (text) => ({
    async init() { const QR = await import('qrcode'); QR.toCanvas(this.$refs.canvas, text, { width: 200, margin: 1, color: { dark: '#070d0b', light: '#ffffff' } }); },
  }));

  /* ── Article picker (related stories, homepage picks) ──────── */
  Alpine.data('articlePicker', (initial = [], { max = 12, locale = '', live = false } = {}) => ({
    items: initial, q: '', results: [], open: false, max,
    async search() {
      if (this.q.length < 2) { this.results = []; return; }
      this.results = await json(`/admin/articles/lookup?q=${encodeURIComponent(this.q)}&locale=${locale}${live ? '&live=1' : ''}`);
      this.open = true;
    },
    add(r) { if (!this.items.find((i) => i.id === r.id) && this.items.length < this.max) this.items.push({ id: r.id, title: r.title }); this.q = ''; this.results = []; this.open = false; },
    remove(i) { this.items.splice(i, 1); },
    move(i, d) { const j = i + d; if (j < 0 || j >= this.items.length) return; [this.items[i], this.items[j]] = [this.items[j], this.items[i]]; },
  }));

  /* ── Story editor ──────────────────────────────────────────── */
  Alpine.data('storyEditor', (cfg) => ({
    tab: cfg.activeLocale,
    t: cfg.translations,
    articleId: cfg.articleId,
    dirty: false,
    saving: false,
    savedAt: null,
    lockedBy: {},
    preview: 'desktop',
    side: false,
    editors: {},
    stats: {},
    gallery: cfg.gallery || [],
    featured: cfg.featured,
    async init() {
      const { createEditor, wordCount } = await import('./editor.js');
      this.wordCount = wordCount;
      for (const l of Object.keys(this.t)) {
        const el = this.$refs['editor_' + l];
        this.editors[l] = createEditor(el, this.t[l].body, (doc) => { this.t[l].body = doc; this.dirty = true; this.stats[l] = wordCount(doc); }, cfg.placeholders[l]);
        this.stats[l] = wordCount(this.t[l].body);
        this.t[l].slugTouched = !!this.t[l].slug;
      }
      window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
      if (this.articleId) {
        this.heartbeat();
        setInterval(() => this.heartbeat(), 60000);
        setInterval(() => this.autosave(), 45000);
        window.addEventListener('pagehide', () => Object.keys(this.t).forEach((l) => navigator.sendBeacon?.(`/admin/articles/${this.articleId}/lock/${l}?_method=DELETE`, new Blob([`_token=${csrf()}`], { type: 'application/x-www-form-urlencoded' }))));
      }
    },
    ed() { return this.editors[this.tab]; },
    cmd(fn) { const e = this.ed(); if (e) { fn(e.chain().focus()).run(); } },
    active(name, attrs) { return this.ed()?.isActive(name, attrs); },
    titleChanged(l) { if (!this.t[l].slugTouched && this.t[l].status !== 'published') this.t[l].slug = slugify(this.t[l].title); this.dirty = true; },
    insertImage() { Alpine.store('media').show((m) => this.cmd((c) => c.setImage({ src: m.full || m.url, alt: m.alt || '', mediaId: m.id }))); },
    insertLink() { const url = prompt('Link URL (https://…)'); if (url === null) return; url ? this.cmd((c) => c.extendMarkRange('link').setLink({ href: url })) : this.cmd((c) => c.unsetLink()); },
    insertYoutube() { const url = prompt('YouTube URL'); if (url) this.cmd((c) => c.setYoutubeVideo({ src: url })); },
    insertEmbed() { const url = prompt('Embed URL (Datawrapper, Flourish, Google Maps, YouTube…)'); if (!url) return; const title = prompt('Short title for this embed') || ''; this.cmd((c) => c.insertContent({ type: 'embed', attrs: { url, title } })); },
    insertPull() { this.cmd((c) => c.insertContent({ type: 'pullQuote', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Quote text' }] }] })); },
    insertFact() { this.cmd((c) => c.insertContent({ type: 'factBox', attrs: { title: 'Key facts' }, content: [{ type: 'bulletList', content: [{ type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Fact' }] }] }] }] })); },
    async insertRelated() {
      const q = prompt('Search for a story to link'); if (!q) return;
      const r = await json(`/admin/articles/lookup?q=${encodeURIComponent(q)}&locale=${this.tab}`);
      if (!r.length) return Alpine.store('toast').push('No matching story', 'err');
      const pick = r[0];
      this.cmd((c) => c.insertContent({ type: 'relatedStory', attrs: { articleId: pick.id, title: pick.title } }));
      Alpine.store('toast').push(`Linked: ${pick.title}`);
    },
    insertTable() { this.cmd((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true })); },
    seoTitle(l) { return (this.t[l].seo_title || this.t[l].title || '').slice(0, 70); },
    seoDesc(l) { return (this.t[l].seo_description || this.t[l].standfirst || '').slice(0, 160); },
    pickFeatured() { Alpine.store('media').show((m) => { this.featured = { id: m.id, url: m.url }; this.dirty = true; }); },
    addGallery() { Alpine.store('media').show((items) => { items.forEach((m) => { if (!this.gallery.find((g) => g.id === m.id)) this.gallery.push({ id: m.id, url: m.url }); }); this.dirty = true; }, { multiple: true }); },
    async heartbeat() {
      for (const l of Object.keys(this.t)) {
        if (!this.t[l].exists) continue;
        try { const r = await json(`/admin/articles/${this.articleId}/lock/${l}`, { method: 'POST' }); this.lockedBy[l] = r.ok ? null : r.locked_by; } catch (_) {}
      }
    },
    async autosave() {
      if (!this.dirty || this.saving) return;
      const l = this.tab;
      if (!this.t[l].title || this.t[l].status === 'published' || this.lockedBy[l]) return;
      try {
        const r = await json(`/admin/articles/${this.articleId}/autosave`, { method: 'POST', body: JSON.stringify({ locale: l, title: this.t[l].title, standfirst: this.t[l].standfirst, body: this.t[l].body }) });
        if (r.ok) { this.t[l].version = r.version; this.savedAt = new Date(); this.t[l].exists = true; }
      } catch (_) {}
    },
    serialize(form) {
      // Write editor JSON into hidden inputs before a normal form submit.
      for (const l of Object.keys(this.t)) {
        const input = form.querySelector(`[name="translations[${l}][body]"]`) || form.querySelector('[name="body"]');
        if (input) input.value = JSON.stringify(this.t[l].body || null);
      }
      this.dirty = false;
      return true;
    },
  }));

  /* ── Homepage layout builder ───────────────────────────────── */
  Alpine.data('homepageBuilder', (layout, types, categories, formats) => ({
    layout, types, categories, formats,
    add(type) { this.layout.sections.push({ type, enabled: true, limit: 4, style: 'grid', key: type === 'category' ? categories[0]?.key : (type === 'format' ? 'opinion' : undefined), slot: type === 'ad' ? 'home_mid' : undefined }); },
    remove(i) { this.layout.sections.splice(i, 1); },
    move(i, d) { const j = i + d; if (j < 0 || j >= this.layout.sections.length) return; const s = this.layout.sections; [s[i], s[j]] = [s[j], s[i]]; },
    label(s) {
      if (s.type === 'category') return this.categories.find((c) => c.key === s.key)?.name || s.key;
      if (s.type === 'format') return this.formats[s.key] || s.key;
      return this.types[s.type];
    },
  }));

  /* ── Menu builder ──────────────────────────────────────────── */
  Alpine.data('menuBuilder', (menus) => ({
    menus, loc: Object.keys(menus)[0],
    add() { this.menus[this.loc].push({ label: '', link_type: 'route', link_value: 'latest', open_new_tab: false, highlight: false }); },
    remove(i) { this.menus[this.loc].splice(i, 1); },
    move(i, d) { const a = this.menus[this.loc]; const j = i + d; if (j < 0 || j >= a.length) return; [a[i], a[j]] = [a[j], a[i]]; },
  }));

  /* ── Dashboard live metrics ────────────────────────────────── */
  Alpine.data('metrics', (initial) => ({
    m: initial,
    init() { setInterval(async () => { try { this.m = await json('/admin/dashboard/metrics'); } catch (_) {} }, 30000); },
  }));

  /* ── Newsroom activity feed (private channel) ──────────────── */
  Alpine.data('activityFeed', (initial) => ({
    items: initial,
    init() {
      window.addEventListener('newsroom:activity', (e) => { this.items.unshift(e.detail); this.items = this.items.slice(0, 15); });
    },
  }));

  /* ── Live desk composer ────────────────────────────────────── */
  Alpine.data('liveComposer', (eventId) => ({
    headline: '', body: '', pinned: false, key: false, media: null, posting: false,
    async post() {
      if (!this.body.trim()) return;
      this.posting = true;
      try {
        await json(`/admin/live/${eventId}/updates`, { method: 'POST', body: JSON.stringify({ headline: this.headline, body: this.body, is_pinned: this.pinned, is_key_event: this.key, media_id: this.media?.id }) });
        Alpine.store('toast').push('Update published live');
        setTimeout(() => location.reload(), 500);
      } catch (e) { Alpine.store('toast').push(e.message, 'err'); } finally { this.posting = false; }
    },
    addPhoto() { Alpine.store('media').show((m) => (this.media = m)); },
  }));
});

/* Real-time newsroom presence */
const cfg = window.DoogsanAdmin || {};
const echo = connectEcho(cfg.reverb);
if (echo) {
  Alpine.store?.('rt', { on: false });
  echo.connector.pusher.connection.bind('connected', () => document.documentElement.dataset.socket = 'on');
  echo.connector.pusher.connection.bind('unavailable', () => document.documentElement.dataset.socket = 'off');
  echo.private('newsroom').listen('.activity', (e) => {
    window.dispatchEvent(new CustomEvent('newsroom:activity', { detail: e }));
    if (e.user !== cfg.user) Alpine.store('toast').push(`${e.user} ${e.message}`, 'info');
  });
}

window.Alpine = Alpine;
Alpine.start();
