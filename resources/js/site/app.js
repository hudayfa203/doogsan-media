/**
 * Doogsan Media — public site runtime.
 * Real-time layer: Laravel Echo (Reverb) when available, otherwise an
 * honest polling fallback against /api/{locale}/pulse. Nothing here
 * fabricates data: every badge and counter comes from the server.
 */
import Alpine from 'alpinejs';
import { connectEcho } from '../shared/echo';

const D = window.Doogsan || {};
const L = D.locale || document.documentElement.lang || 'en';
const T = D.i18n || {};

/* ── Time ─────────────────────────────────────────────────────── */
const TZ = D.timezone || 'Africa/Mogadishu';
const intlLocale = L === 'so' ? 'so-SO' : 'en-GB';

function relTime(iso) {
  const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return T.just_now || 'Just now';
  const plural = (n, one, many) => (n === 1 ? one : many).replace(':n', n);
  if (s < 3600) { const n = Math.floor(s / 60); return plural(n, T.minute || ':n minute ago', T.minutes || ':n minutes ago'); }
  if (s < 86400) { const n = Math.floor(s / 3600); return plural(n, T.hour || ':n hour ago', T.hours || ':n hours ago'); }
  if (s < 604800) { const n = Math.floor(s / 86400); return plural(n, T.day || ':n day ago', T.days || ':n days ago'); }
  return null; // keep server-rendered absolute date
}

function tickRelative(root = document) {
  root.querySelectorAll('time[data-rel]').forEach((el) => {
    const v = relTime(el.getAttribute('datetime'));
    if (v) el.textContent = v;
  });
}

function formatClock(date) {
  const time = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hour12: false }).format(date);
  const parts = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, weekday: 'long', day: 'numeric', month: 'numeric', year: 'numeric' }).formatToParts(date);
  const get = (t) => parts.find((p) => p.type === t)?.value;
  const weekday = (T.weekdays || {})[get('weekday')] || get('weekday');
  const month = (T.months || [])[Number(get('month')) - 1] || get('month');
  return { time, date: `${weekday}, ${get('day')} ${month} ${get('year')}` };
}

/* ── Theme ────────────────────────────────────────────────────── */
function applyTheme(t) {
  document.documentElement.dataset.theme = t;
  document.querySelector('meta[name=theme-color]')?.setAttribute('content', t === 'dark' ? '#021c17' : '#04322a');
}

/* ── Analytics beacon (first-party, cookieless) ───────────────── */
function beacon() {
  const c = D.content || {};
  const body = JSON.stringify({ locale: L, type: c.type || 'page', id: c.id || null, path: location.pathname, ref: document.referrer || null });
  try {
    if (navigator.sendBeacon) navigator.sendBeacon('/api/beacon', new Blob([body], { type: 'application/json' }));
    else fetch('/api/beacon', { method: 'POST', body, headers: { 'Content-Type': 'application/json' }, keepalive: true });
  } catch (_) { /* analytics must never break reading */ }
}

/* ── Alpine components ────────────────────────────────────────── */
document.addEventListener('alpine:init', () => {
  Alpine.store('live', {
    alerts: D.alerts || [],
    tvOnAir: !!D.tvOnAir,
    connected: false,
    mode: 'connecting', // socket | polling
    latestId: D.latestId || null,
    liveNow: !!(D.liveEvents && D.liveEvents.length),
    newStories: [],
    setAlerts(a) { this.alerts = (a || []).filter((x) => !x.expires_at || new Date(x.expires_at) > new Date()); },
    get urgent() { return this.alerts.find((a) => a.urgent); },
  });

  Alpine.data('clock', () => ({
    time: '', date: '',
    init() { const u = () => Object.assign(this, formatClock(new Date())); u(); setInterval(u, 1000 * 15); },
  }));

  Alpine.data('themeToggle', () => ({
    dark: document.documentElement.dataset.theme === 'dark',
    toggle() {
      this.dark = !this.dark;
      const t = this.dark ? 'dark' : 'light';
      try { localStorage.setItem('theme', t); } catch (_) {}
      applyTheme(t);
    },
  }));

  Alpine.data('share', (url, title) => ({
    copied: false,
    canNative: !!navigator.share,
    async native() { try { await navigator.share({ title, url }); } catch (_) {} },
    async copy() {
      try { await navigator.clipboard.writeText(url); } catch (_) {
        const i = document.createElement('input'); i.value = url; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove();
      }
      this.copied = true; setTimeout(() => (this.copied = false), 2200);
    },
  }));

  /* Liveblog timeline: socket updates for this event, polling fallback. */
  Alpine.data('liveblog', (eventId, status) => ({
    status, pending: [], lastPoll: new Date().toISOString(),
    init() {
      window.addEventListener('doogsan:socket', (e) => {
        const echo = e.detail;
        echo.channel(`live-event.${eventId}`)
          .listen('.update.posted', (u) => this.queue(u))
          .listen('.update.edited', (u) => this.replace(u))
          .listen('.update.deleted', (u) => document.getElementById(`u-${u.id}`)?.remove())
          .listen('.live.status', (s) => { this.status = s.status; });
      });
      setInterval(() => { if (Alpine.store('live').mode !== 'socket') this.poll(); }, (D.pollInterval || 45) * 1000);
    },
    async poll() {
      try {
        const r = await fetch(`/api/live/${eventId}/updates?after=${encodeURIComponent(this.lastPoll)}`, { headers: { Accept: 'application/json' } });
        const j = await r.json();
        this.lastPoll = new Date().toISOString();
        this.status = j.status;
        (j.updates || []).forEach((u) => (document.getElementById(`u-${u.id}`) ? this.replace(u) : this.queue(u)));
        const keep = new Set((j.ids || []).map(String));
        document.querySelectorAll('[data-update-id]').forEach((el) => { if (!keep.has(el.dataset.updateId)) el.remove(); });
      } catch (_) {}
    },
    queue(u) { if (!document.getElementById(`u-${u.id}`)) { this.pending.unshift(u); if (window.scrollY < 400) this.flush(); } },
    flush() {
      const list = this.$refs.list;
      this.pending.reverse().forEach((u) => {
        const tpl = this.$refs.tpl.content.cloneNode(true);
        this.fill(tpl.firstElementChild, u);
        const firstUnpinned = list.querySelector('[data-update-id]:not([data-pinned])');
        list.insertBefore(tpl, firstUnpinned);
      });
      this.pending = [];
      tickRelative(list);
    },
    fill(el, u) {
      el.id = `u-${u.id}`; el.dataset.updateId = u.id;
      el.classList.add('is-new'); if (u.is_key_event) el.classList.add('is-key');
      el.querySelector('[data-f=time]').setAttribute('datetime', u.posted_at);
      el.querySelector('[data-f=time]').textContent = relTime(u.posted_at) || '';
      el.querySelector('[data-f=headline]').textContent = u.headline || '';
      el.querySelector('[data-f=headline]').hidden = !u.headline;
      el.querySelector('[data-f=body]').innerHTML = u.body; // sanitized server-side
      el.querySelector('[data-f=author]').textContent = u.author || '';
      const img = el.querySelector('[data-f=image]');
      if (u.image) { img.src = u.image; img.hidden = false; } else img.hidden = true;
    },
    replace(u) { const el = document.getElementById(`u-${u.id}`); if (el) this.fill(el, u); },
  }));

  /* Mobile compact "Latest": two newest headlines, auto-updating. */
  Alpine.data('latestCompact', (initial) => ({
    all: initial,
    get items() { return this.all.slice(0, 2); },
    init() {
      window.addEventListener('doogsan:article', (e) => {
        const a = e.detail;
        if (this.all.find((x) => x.id === a.id)) return;
        this.all.unshift({ id: a.id, title: a.title, url: a.url, published_at: a.published_at, fresh: true });
      });
      setInterval(() => { this.all = [...this.all]; }, 60000); // refresh short times
    },
    short(iso) {
      const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
      if (s < 60) return T.now_short || 'now';
      if (s < 3600) return Math.floor(s / 60) + (T.min_short || 'm');
      if (s < 86400) return Math.floor(s / 3600) + (T.hour_short || 'h');
      return Math.floor(s / 86400) + (T.day_short || 'd');
    },
  }));

  /* Homepage "Latest" stream: prepends newly published stories. */
  Alpine.data('latestStream', () => ({
    fresh: [],
    init() {
      window.addEventListener('doogsan:article', (e) => {
        if (!this.fresh.find((x) => x.id === e.detail.id)) this.fresh.unshift(e.detail);
        this.$nextTick(() => tickRelative(this.$el));
      });
    },
    rel: (iso) => relTime(iso) || '',
  }));

  Alpine.data('tvPlayer', (src, type) => ({
    error: false,
    init() {
      if (type !== 'hls' || !src) return;
      const v = this.$refs.video;
      if (v.canPlayType('application/vnd.apple.mpegurl')) { v.src = src; return; }
      import('hls.js').then(({ default: Hls }) => {
        if (!Hls.isSupported()) { this.error = true; return; }
        const hls = new Hls({ lowLatencyMode: true });
        hls.on(Hls.Events.ERROR, (_, d) => { if (d.fatal) this.error = true; });
        hls.loadSource(src); hls.attachMedia(v);
      });
    },
  }));

  Alpine.data('lightbox', () => ({
    open: false, src: '', caption: '',
    init() {
      document.addEventListener('click', (e) => {
        const img = e.target.closest('img[data-zoom]');
        if (!img) return;
        this.src = img.currentSrc || img.src;
        this.caption = img.closest('figure')?.querySelector('figcaption')?.textContent || img.alt;
        this.open = true;
      });
    },
  }));

  /* Opt-in browser notifications for breaking news (open tabs only). */
  Alpine.data('notifyOptIn', () => ({
    supported: 'Notification' in window,
    state: 'Notification' in window ? Notification.permission : 'unsupported',
    async enable() { this.state = await Notification.requestPermission(); },
  }));
});

/* ── Real-time wiring ────────────────────────────────────────── */
function notifyBreaking(alerts, previous) {
  const prevIds = new Set(previous.map((a) => a.id));
  const fresh = alerts.filter((a) => !prevIds.has(a.id) && a.priority === 1);
  if (!fresh.length || !('Notification' in window) || Notification.permission !== 'granted') return;
  fresh.forEach((a) => {
    const n = new Notification(T.breaking || 'Breaking news', { body: a.headline, icon: '/images/brand/icon-192.png', tag: `breaking-${a.id}` });
    if (a.url) n.onclick = () => { window.focus(); location.href = a.url; };
  });
}

function startPolling() {
  const store = Alpine.store('live');
  store.mode = 'polling';
  const run = async () => {
    try {
      const r = await fetch(`/api/${L}/pulse`, { headers: { Accept: 'application/json' } });
      const j = await r.json();
      const prev = store.alerts;
      store.setAlerts(j.alerts);
      notifyBreaking(store.alerts, prev);
      store.tvOnAir = j.tv_on_air;
      store.liveNow = !!(j.live_events && j.live_events.length);
      if (j.latest_id && store.latestId && j.latest_id > store.latestId) {
        const lr = await fetch(`/api/${L}/latest?after=${store.latestId}`, { headers: { Accept: 'application/json' } });
        (await lr.json()).reverse().forEach((a) => window.dispatchEvent(new CustomEvent('doogsan:article', { detail: a })));
      }
      if (j.latest_id) store.latestId = Math.max(store.latestId || 0, j.latest_id);
    } catch (_) {}
  };
  setInterval(() => { if (store.mode === 'polling' && document.visibilityState === 'visible') run(); }, (D.pollInterval || 45) * 1000);
}

function startRealtime() {
  const store = Alpine.store('live');
  const echo = connectEcho(D.reverb);
  if (!echo) { startPolling(); return; }

  const pusher = echo.connector.pusher;
  pusher.connection.bind('connected', () => { store.connected = true; store.mode = 'socket'; });
  pusher.connection.bind('unavailable', () => { store.connected = false; store.mode = 'polling'; });
  pusher.connection.bind('failed', () => { store.connected = false; store.mode = 'polling'; });

  echo.channel(`edition.${L}`)
    .listen('.breaking.changed', (e) => { const prev = store.alerts; store.setAlerts(e.alerts); notifyBreaking(store.alerts, prev); })
    .listen('.article.published', (a) => { store.latestId = Math.max(store.latestId || 0, a.id); window.dispatchEvent(new CustomEvent('doogsan:article', { detail: a })); })
    .listen('.tv.status', (e) => { store.tvOnAir = e.on_air; });

  window.dispatchEvent(new CustomEvent('doogsan:socket', { detail: echo }));
  startPolling(); // runs only while the socket is down
  store.mode = 'connecting';
}

/* Expired alerts disappear without a page refresh. */
setInterval(() => { const s = Alpine.store('live'); if (s) s.setAlerts(s.alerts); }, 30000);

window.Alpine = Alpine;
Alpine.start();
startRealtime();
tickRelative();
setInterval(() => tickRelative(), 30000);
if (document.readyState === 'complete') beacon(); else window.addEventListener('load', beacon);

if ('serviceWorker' in navigator && D.pwa) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
