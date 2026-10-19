@extends('layouts.admin')
@section('title', 'SEO Center')

@section('content')
<x-admin.page-header eyebrow="Distribution" title="SEO Center" subtitle="Search health across both editions, verification codes, robots rules and redirects.">
    @foreach (editions() as $l => $e)
        <a href="{{ url($l.'/sitemap.xml') }}" target="_blank" class="btn btn-secondary btn-sm">{{ strtoupper($l) }} sitemap ↗</a>
    @endforeach
</x-admin.page-header>

@php
    $issueLabels = [
        'missing_description' => ['Missing description', 'No SEO description or standfirst'],
        'long_title' => ['Title too long', 'Over 70 characters — truncated in results'],
        'no_image' => ['No featured image', 'Weak social previews, excluded from Top Stories'],
        'noindex' => ['Hidden from search', 'Marked noindex by an editor'],
    ];
@endphp
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ($issueLabels as $k => [$label, $hint])
        <div class="panel p-4">
            <p class="eyebrow">{{ $label }}</p>
            <p class="num mt-1 text-2xl font-bold {{ ($issues[$k] ?? 0) > 0 && $k !== 'noindex' ? 'text-[var(--color-amber)]' : 'text-white' }}">{{ number_format($issues[$k] ?? 0) }}</p>
            <p class="mt-1 text-xs text-[var(--color-ink-400)]">{{ $hint }}</p>
        </div>
    @endforeach
</div>

@if ($settings['block_indexing'])
    <div class="mb-6 rounded-lg border border-[var(--color-alarm)]/50 bg-[color-mix(in_oklab,var(--color-alarm)_10%,transparent)] px-4 py-3 text-sm font-semibold text-[#ffb3ad]">Search engines are currently blocked from indexing the whole site. Turn this off before launch.</div>
@endif

<div class="grid gap-6 xl:grid-cols-2">
    <section class="panel">
        <div class="panel-head"><h2>Published stories needing attention</h2></div>
        <table class="table"><tbody>
            @forelse ($needsWork as $t)
                @php $len = mb_strlen($t->seo_title ?: $t->title); @endphp
                <tr>
                    <td class="w-10 text-[0.625rem] font-extrabold uppercase">{{ $t->locale }}</td>
                    <td lang="{{ $t->locale }}"><a href="{{ story_edit_url($t->article_id, $t->article?->edition) }}" class="font-semibold text-white hover:text-[var(--color-signal)]">{{ \Illuminate\Support\Str::limit($t->title, 90) }}</a>
                        <p class="mt-0.5 flex flex-wrap gap-2 text-[0.6875rem]">
                            @if (! $t->seo_description && ! $t->standfirst)<span class="text-[var(--color-amber)]">no description</span>@endif
                            @if ($len > 70)<span class="text-[var(--color-amber)]">title {{ $len }} chars</span>@endif
                        </p></td>
                    <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ $t->published_at ? local_time($t->published_at)->format('d M') : '' }}</td>
                </tr>
            @empty
                <tr><td class="p-6 text-sm text-[var(--color-ink-400)]">No issues found in published stories.</td></tr>
            @endforelse
        </tbody></table>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Search settings</h2></div>
        <form method="post" action="{{ route('admin.seo.settings') }}" class="panel-body space-y-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="label">Google Search Console verification</label><input name="google_site_verification" value="{{ old('google_site_verification', $settings['google_site_verification']) }}" class="input" maxlength="120" placeholder="content value only"></div>
                <div><label class="label">Bing Webmaster verification</label><input name="bing_site_verification" value="{{ old('bing_site_verification', $settings['bing_site_verification']) }}" class="input" maxlength="120"></div>
                <div><label class="label">Google Analytics ID <span class="font-normal text-[var(--color-ink-500)]">(optional)</span></label><input name="google_analytics_id" value="{{ old('google_analytics_id', $settings['google_analytics_id']) }}" class="input" placeholder="G-XXXXXXX"><p class="hint">Built-in analytics are cookieless; adding GA may require a cookie notice.</p></div>
                <div><label class="label">Default social share image URL</label><input name="default_og_image" type="url" value="{{ old('default_og_image', $settings['default_og_image']) }}" class="input" placeholder="https://"></div>
            </div>
            <div><label class="label">Extra robots.txt rules</label><textarea name="robots_txt" rows="5" class="textarea num text-xs" maxlength="4000" placeholder="Disallow: /en/search">{{ old('robots_txt', $settings['robots_txt']) }}</textarea><p class="hint">Appended to the generated robots.txt, which already lists both sitemaps.</p></div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="block_indexing" value="1" class="check mt-0.5" @checked($settings['block_indexing'])> <span><strong>Block all search indexing</strong><br><span class="text-xs text-[var(--color-ink-400)]">For staging only (e.g. new.doogsanmedia.com).</span></span></label>
            <button class="btn btn-primary">Save settings</button>
        </form>
    </section>
</div>

<section class="panel mt-6">
    <div class="panel-head"><h2>Redirects</h2><span class="text-xs text-[var(--color-ink-400)]">Old URLs keep their search ranking when redirected with 301</span></div>
    <form method="post" action="{{ route('admin.seo.redirects.store') }}" class="panel-body grid gap-3 border-b border-[var(--color-ink-700)] md:grid-cols-[1fr_1fr_8rem_auto]">
        @csrf
        <input name="from_path" required class="input" placeholder="/2024/05/old-wordpress-post/">
        <input name="to_url" required class="input" placeholder="/en/news/new-slug or https://…">
        <select name="status_code" class="select"><option value="301">301 permanent</option><option value="302">302 temporary</option></select>
        <button class="btn btn-primary">Add redirect</button>
    </form>
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th></th></tr></thead>
            <tbody>
            @forelse ($redirects as $r)
                <tr>
                    <td class="num text-xs">{{ $r->from_path }}</td>
                    <td class="num text-xs text-[var(--color-ink-300)]">{{ $r->to_url }}</td>
                    <td><span class="pill pill-draft">{{ $r->status_code }}</span></td>
                    <td class="num text-xs">{{ number_format($r->hits ?? 0) }}</td>
                    <td class="text-right"><form method="post" action="{{ route('admin.seo.redirects.destroy', $r) }}" data-confirm="Remove this redirect?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Remove</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-sm text-[var(--color-ink-400)]">No redirects yet. The WordPress importer creates them automatically for every imported post.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $redirects->links('admin.partials.pagination') }}</div>
</section>
@endsection
