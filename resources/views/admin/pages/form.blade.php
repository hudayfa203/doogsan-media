@extends('layouts.admin')
@section('title', 'Edit page — '.$page->key)

@php
    /* Turn stored HTML into the simple writing format used by this form (## headings, - lists, **bold**, [links](url)). */
    $toText = function (?string $html): string {
        if (! trim((string) $html)) return '';
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="r">'.$html.'</div>');
        libxml_clear_errors();
        $inline = function (\DOMNode $n) use (&$inline): string {
            $out = '';
            foreach ($n->childNodes as $c) {
                if ($c instanceof \DOMText) { $out .= preg_replace('/\s+/u', ' ', $c->nodeValue); continue; }
                $tag = strtolower($c->nodeName);
                $inner = $inline($c);
                $out .= match ($tag) {
                    'a' => '['.$inner.']('.$c->getAttribute('href').')',
                    'strong', 'b' => '**'.$inner.'**',
                    'em', 'i' => '_'.$inner.'_',
                    'br' => "\n",
                    default => $inner,
                };
            }
            return $out;
        };
        $blocks = [];
        foreach ($doc->getElementById('r')->childNodes as $n) {
            $tag = strtolower($n->nodeName);
            if ($n instanceof \DOMText) { if (trim($n->nodeValue)) $blocks[] = trim($n->nodeValue); continue; }
            if (in_array($tag, ['ul', 'ol'], true)) {
                $items = [];
                $i = 1;
                foreach ($n->childNodes as $li) if (strtolower($li->nodeName) === 'li') $items[] = ($tag === 'ol' ? ($i++).'. ' : '- ').trim($inline($li));
                $blocks[] = implode("\n", $items);
            } elseif (preg_match('/^h([2-4])$/', $tag, $m)) {
                $blocks[] = str_repeat('#', (int) $m[1]).' '.trim($inline($n));
            } else {
                $blocks[] = trim($inline($n));
            }
        }
        return implode("\n\n", array_filter($blocks, fn ($b) => $b !== ''));
    };
@endphp

@section('content')
<x-admin.page-header eyebrow="Pages" :title="$page->t('en')?->title ?? $page->key" subtitle="Write each edition separately. Use ## for a heading, - for a list item, **bold**, _italic_ and [link text](https://…).">
    <a href="{{ route('admin.pages.index') }}" class="btn btn-ghost">← All pages</a>
</x-admin.page-header>

<form method="post" action="{{ route('admin.pages.update', $page) }}" x-data="pageForm()" @submit="serialize()" class="space-y-6">
    @csrf @method('PUT')
    <div class="grid gap-6 xl:grid-cols-2">
        @foreach (editions() as $l => $e)
            @php $t = $page->t($l); @endphp
            <section class="panel" lang="{{ $l }}">
                <div class="panel-head"><h2>{{ $e['name'] }}</h2>
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="published[{{ $l }}]" value="1" class="check" @checked(old("published.$l", $t?->is_published ?? true))> Published</label></div>
                <div class="panel-body space-y-3">
                    <div><label class="label">Title</label><input name="t[{{ $l }}][title]" value="{{ old("t.$l.title", $t?->title) }}" required maxlength="160" class="input !text-base font-semibold"></div>
                    <div><label class="label">Address (slug)</label>
                        <div class="flex items-center gap-1"><span class="text-xs text-[var(--color-ink-500)]">/{{ $l }}/…/</span><input name="t[{{ $l }}][slug]" value="{{ old("t.$l.slug", $t?->slug) }}" pattern="[a-z0-9\-]*" maxlength="120" class="input font-mono"></div></div>
                    <div><label class="label">Summary</label><textarea name="t[{{ $l }}][summary]" rows="2" maxlength="500" class="textarea">{{ old("t.$l.summary", $t?->summary) }}</textarea></div>
                    <div><label class="label">Page text</label>
                        <textarea x-ref="src_{{ $l }}" rows="18" class="textarea font-mono !text-[0.8125rem] leading-relaxed">{{ $toText($t?->body_html) }}</textarea>
                        <input type="hidden" name="t[{{ $l }}][body]" x-ref="body_{{ $l }}"></div>
                    <div><label class="label">Search description</label><input name="t[{{ $l }}][seo_description]" value="{{ old("t.$l.seo_description", $t?->seo_description) }}" maxlength="255" class="input"></div>
                </div>
            </section>
        @endforeach
    </div>
    <div class="panel panel-body flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="show_in_footer" value="1" class="check" @checked($page->show_in_footer)> Show in footer</label>
        <button class="btn btn-primary">Save page</button>
    </div>
</form>

<script>
function pageForm() {
    const inline = (s) => {
        const out = [];
        const re = /\[([^\]]+)\]\(([^)\s]+)\)|\*\*([^*]+)\*\*|_([^_]+)_/g;
        let last = 0, m;
        while ((m = re.exec(s))) {
            if (m.index > last) out.push({ type: 'text', text: s.slice(last, m.index) });
            if (m[1]) out.push({ type: 'text', text: m[1], marks: [{ type: 'link', attrs: { href: m[2] } }] });
            else if (m[3]) out.push({ type: 'text', text: m[3], marks: [{ type: 'bold' }] });
            else out.push({ type: 'text', text: m[4], marks: [{ type: 'italic' }] });
            last = re.lastIndex;
        }
        if (last < s.length) out.push({ type: 'text', text: s.slice(last) });
        return out.filter((n) => n.text !== '');
    };
    const para = (text) => {
        const parts = text.split('\n');
        const content = [];
        parts.forEach((p, i) => { content.push(...inline(p)); if (i < parts.length - 1) content.push({ type: 'hardBreak' }); });
        return { type: 'paragraph', content };
    };
    const toDoc = (src) => {
        const blocks = src.replace(/\r/g, '').split(/\n{2,}/).map((b) => b.trim()).filter(Boolean);
        return { type: 'doc', content: blocks.map((b) => {
            const h = b.match(/^(#{2,4})\s+(.*)$/s);
            if (h) return { type: 'heading', attrs: { level: h[1].length }, content: inline(h[2].replace(/\n/g, ' ')) };
            const lines = b.split('\n');
            if (lines.every((l) => /^[-*]\s+/.test(l))) return { type: 'bulletList', content: lines.map((l) => ({ type: 'listItem', content: [para(l.replace(/^[-*]\s+/, ''))] })) };
            if (lines.every((l) => /^\d+\.\s+/.test(l))) return { type: 'orderedList', content: lines.map((l) => ({ type: 'listItem', content: [para(l.replace(/^\d+\.\s+/, ''))] })) };
            return para(b);
        }) };
    };
    return {
        serialize() {
            @foreach (array_keys(editions()) as $l)
            this.$refs.body_{{ $l }}.value = JSON.stringify(toDoc(this.$refs.src_{{ $l }}.value));
            @endforeach
        },
    };
}
</script>
@endsection
