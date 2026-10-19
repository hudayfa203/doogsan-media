@php
    $locale = edition();
    $other = $locale === 'en' ? 'so' : 'en';
    $alternates = $seo->alternates ?: [];
    $switchUrl = $alternates[$other] ?? lroute('home', [], $other);
    $hasTranslation = isset($alternates[$other]);
    $canonical = $seo->canonical ?: url()->current();
    $ogImage = $seo->image ?: (setting('default_og_image') ?: asset('images/brand/og-default.jpg'));
    $reverb = config('broadcasting.default') === 'reverb' ? [
        'key' => config('broadcasting.connections.reverb.key'),
        'host' => config('broadcasting.connections.reverb.options.host'),
        'port' => (int) config('broadcasting.connections.reverb.options.port'),
        'scheme' => config('broadcasting.connections.reverb.options.scheme'),
    ] : (config('broadcasting.default') === 'pusher' ? [
        'driver' => 'pusher',
        'key' => config('broadcasting.connections.pusher.key'),
        'cluster' => config('broadcasting.connections.pusher.options.cluster'),
    ] : null);
    $jsConfig = [
            'locale' => $locale,
            'timezone' => config('doogsan.timezone'),
            'pollInterval' => config('doogsan.poll_interval'),
            'reverb' => $reverb,
            'alerts' => $pulse['alerts'],
            'latestId' => $pulse['latest_id'],
            'tvOnAir' => $pulse['tv_on_air'],
        'liveEvents' => $pulse['live_events'],
            'pwa' => (bool) ($features['pwa'] ?? true),
            'content' => $seo->content,
            'i18n' => trans('js'),
        ];
@endphp
<!DOCTYPE html>
<html lang="{{ editions()[$locale]['html_lang'] }}" dir="ltr" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $seo->fullTitle() }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $seo->description), 300) }}">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternates as $l => $href)
        <link rel="alternate" hreflang="{{ editions()[$l]['hreflang'] }}" href="{{ $href }}">
    @endforeach
    @if (isset($alternates[config('doogsan.default_edition')]))
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[config('doogsan.default_edition')] }}">
    @endif
    @if ($seo->noindex || setting('block_indexing') || app()->environment('staging'))
        <meta name="robots" content="noindex, nofollow">
    @else
        <meta name="robots" content="index, follow, max-image-preview:large">
    @endif
    <meta property="og:site_name" content="Doogsan Media">
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:title" content="{{ $seo->title ?: $seo->fullTitle() }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $seo->description), 200) }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ editions()[$locale]['og_locale'] }}">
    @if ($seo->publishedTime)<meta property="article:published_time" content="{{ $seo->publishedTime }}">@endif
    @if ($seo->modifiedTime)<meta property="article:modified_time" content="{{ $seo->modifiedTime }}">@endif
    @if ($seo->section)<meta property="article:section" content="{{ $seo->section }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    @if (setting('google_site_verification'))<meta name="google-site-verification" content="{{ setting('google_site_verification') }}">@endif
    @if (setting('bing_site_verification'))<meta name="msvalidate.01" content="{{ setting('bing_site_verification') }}">@endif
    <meta name="theme-color" content="#04322a">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/brand/favicon-32.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ route('manifest') }}">
    <link rel="alternate" type="application/rss+xml" title="Doogsan Media ({{ editions()[$locale]['native'] }})" href="{{ lroute('rss') }}">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
        window.Doogsan = {!! json_encode($jsConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};
    </script>
    @foreach ($seo->jsonLd as $ld)
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endforeach
    @vite(['resources/css/site.css', 'resources/js/site/app.js'])
    @if (setting('google_analytics_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ setting('google_analytics_id') }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ setting('google_analytics_id') }}',{anonymize_ip:true});</script>
    @endif
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:text-black focus:px-3 focus:py-2">{{ __('Skip to content') }}</a>

{{-- ── Urgent banner (sitewide, editor-controlled) ─────────────────── --}}
<div x-data x-cloak x-show="$store.live.urgent" class="bg-[var(--color-alert-600)] text-white no-print" role="alert">
    <template x-if="$store.live.urgent">
        <a :href="$store.live.urgent.url || '{{ lroute('breaking') }}'" class="wrap flex items-center gap-3 py-2.5 text-sm font-bold">
            <span class="badge bg-white text-[var(--color-alert-700)]"><span class="live-dot"></span>{{ __('Urgent') }}</span>
            <span class="truncate" x-text="$store.live.urgent.headline"></span>
            <svg class="ml-auto h-4 w-4 flex-none" viewBox="0 0 20 20" fill="currentColor"><path d="M7.3 4.3a1 1 0 0 1 1.4 0l5 5a1 1 0 0 1 0 1.4l-5 5a1 1 0 0 1-1.4-1.4L11.6 10 7.3 5.7a1 1 0 0 1 0-1.4Z"/></svg>
        </a>
    </template>
</div>

{{-- ── Utility bar ───────────────────────────────────────────────── --}}
<div class="masthead border-b border-white/10 text-[0.75rem] no-print">
    <div class="wrap flex h-9 items-center gap-4">
        <div x-data="clock" class="hidden sm:flex items-center gap-2 text-white/80 tabular-nums">
            <span x-text="date">{{ news_date(now(), false) }}</span>
            <span class="text-white/30">|</span>
            <span><span x-text="time">{{ local_time(now())->format('H:i') }}</span> <span class="text-white/50">{{ __('Mogadishu') }}</span></span>
        </div>
        <a x-data x-cloak x-show="$store.live.tvOnAir" href="{{ lroute('tv') }}" class="flex items-center gap-1.5 font-extrabold uppercase tracking-wider text-[#ff8a80]">
            <span class="live-dot"></span> {{ __('Live TV') }}
        </a>
        <div class="ml-auto flex items-center gap-3">
            @if ($social)
                <div class="hidden md:flex items-center gap-2.5">
                    @foreach ($social as $network => $url)
                        <a href="{{ $url }}" rel="noopener" target="_blank" class="text-white/60 hover:text-[var(--color-lime-400)]" aria-label="{{ ucfirst($network) }}">
                            <x-social-icon :network="$network" class="h-3.5 w-3.5" />
                        </a>
                    @endforeach
                </div>
            @endif
            <nav aria-label="{{ __('Language edition') }}" class="flex items-center rounded-full bg-white/10 p-0.5 font-bold">
                @foreach (editions() as $code => $ed)
                    @if ($code === $locale)
                        <span class="rounded-full bg-[var(--color-lime-400)] px-2.5 py-0.5 text-[var(--color-forest-950)]" aria-current="true">{{ $ed['native'] }}</span>
                    @else
                        <a href="{{ $hasTranslation ? $switchUrl : lroute('home', [], $code) }}" hreflang="{{ $code }}" lang="{{ $code }}" class="rounded-full px-2.5 py-0.5 text-white/80 hover:text-white">{{ $ed['native'] }}</a>
                    @endif
                @endforeach
            </nav>
            @if ($features['dark_mode'] ?? true)
                <button x-data="themeToggle" @click="toggle" class="text-white/70 hover:text-white" :aria-label="dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'">
                    <svg x-show="!dark" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg x-show="dark" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
            @endif
        </div>
    </div>
</div>

{{-- ── Masthead ──────────────────────────────────────────────────── --}}
<header x-data="{ menu: false, search: false }" class="masthead sticky top-0 z-40 no-print" @keydown.escape.window="menu = false; search = false">
    <div class="wrap flex items-center gap-4 h-16 lg:h-[4.5rem]">
        <button @click="menu = true" class="lg:hidden -ml-1 p-1.5 text-white" aria-label="{{ __('Open menu') }}">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
        </button>
        <a href="{{ lroute('home') }}" class="flex-none" aria-label="Doogsan Media — {{ __('Home') }}">
            <img src="{{ brand_logo('dark') }}" alt="Doogsan Media" class="h-9 lg:h-11 w-auto" width="160" height="47">
        </a>
        <div class="hidden lg:block h-8 w-px bg-white/15 mx-1"></div>
        <p class="hidden xl:block text-[0.75rem] leading-tight text-white/60 max-w-[14rem]">{{ lsetting('tagline', __('Independent news from Puntland, Somalia and the world')) }}</p>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ lroute('live') }}" class="hidden md:inline-flex items-center gap-2 rounded-sm border border-white/20 px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider text-white hover:border-white/50">
                <span class="live-dot text-[#ff6b5e]"></span>{{ __('Live') }}
            </a>
            <a href="{{ lroute('newsletter') }}" class="hidden md:inline-flex items-center rounded-sm bg-[var(--color-lime-400)] px-3 py-1.5 text-xs font-extrabold text-[var(--color-forest-950)] hover:bg-[var(--color-lime-300)]">{{ __('Newsletter') }}</a>
            <button @click="search = !search; $nextTick(() => $refs.q?.focus())" class="p-2 text-white" aria-label="{{ __('Search') }}" :aria-expanded="search">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </button>
        </div>
    </div>

    {{-- Search drawer --}}
    <div x-cloak x-show="search" x-transition.opacity class="border-t border-white/10 bg-[var(--color-forest-950)]">
        <form action="{{ lroute('search') }}" method="get" class="wrap flex gap-2 py-3" role="search">
            <input x-ref="q" name="q" type="search" placeholder="{{ __('Search Doogsan Media') }}" class="flex-1 rounded-sm border-0 bg-white/10 px-4 py-2.5 text-white placeholder:text-white/40 focus:ring-2 focus:ring-[var(--color-lime-400)]">
            <button class="btn bg-[var(--color-lime-400)] text-[var(--color-forest-950)]">{{ __('Search') }}</button>
        </form>
    </div>

    {{-- Primary navigation --}}
    <nav class="hidden lg:block bg-[var(--bg)] border-b border-[var(--rule)]" aria-label="{{ __('Sections') }}">
        <div class="wrap flex items-center gap-6 scroll-x">
            <a href="{{ lroute('home') }}" class="nav-link" @if (request()->routeIs("{$locale}.home")) aria-current="page" @endif>{{ __('Home') }}</a>
            @foreach ($menus['primary'] ?? [] as $item)
                <a href="{{ $item['href'] }}" class="nav-link {{ $item['highlight'] ? 'text-[var(--accent)]' : '' }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif @if (url()->current() === $item['href']) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </div>
    </nav>

    {{-- Mobile drawer --}}
    <div x-cloak x-show="menu" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
        <div x-show="menu" x-transition.opacity @click="menu = false" class="absolute inset-0 bg-black/50"></div>
        <div x-show="menu" x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="-translate-x-full"
             class="absolute inset-y-0 left-0 w-[86%] max-w-sm overflow-y-auto bg-[var(--bg)] text-[var(--fg)] shadow-2xl">
            <div class="masthead flex items-center justify-between px-4 h-16">
                <img src="{{ brand_logo('dark') }}" alt="Doogsan Media" class="h-8 w-auto">
                <button @click="menu = false" class="p-2 text-white" aria-label="{{ __('Close menu') }}">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="p-4 space-y-6">
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ lroute('live') }}" class="flex items-center justify-center gap-2 rounded-sm bg-[var(--color-alert-600)] py-2.5 text-sm font-extrabold text-white"><span class="live-dot"></span>{{ __('Live') }}</a>
                    <a href="{{ lroute('tv') }}" class="flex items-center justify-center gap-2 rounded-sm bg-[var(--color-forest-900)] py-2.5 text-sm font-extrabold text-white">{{ __('Live TV') }}</a>
                </div>
                <div>
                    <p class="kicker mb-2">{{ __('Sections') }}</p>
                    <ul class="divide-y divide-[var(--rule)] border-y border-[var(--rule)]">
                        <li><a href="{{ lroute('latest') }}" class="block py-3 font-bold">{{ __('Latest News') }}</a></li>
                        @foreach ($menus['primary'] ?? [] as $item)
                            <li><a href="{{ $item['href'] }}" class="block py-3 font-bold">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <p class="kicker mb-2">{{ __('Journalism') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['analysis', 'opinion', 'investigation', 'in-depth', 'interview', 'fact-check'] as $f)
                            <a href="{{ lroute('format', ['slug' => config("doogsan.formats.{$f}.slug.{$locale}")]) }}" class="rounded-full border border-[var(--rule)] px-3 py-1.5 text-sm font-semibold">{{ format_label($f) }}</a>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="kicker mb-2">{{ __('Watch & Listen') }}</p>
                    <ul class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm font-semibold">
                        <li><a href="{{ lroute('videos') }}">{{ __('Videos') }}</a></li>
                        <li><a href="{{ lroute('documentaries') }}">{{ __('Documentaries') }}</a></li>
                        <li><a href="{{ lroute('podcasts') }}">{{ __('Podcasts') }}</a></li>
                        <li><a href="{{ lroute('galleries') }}">{{ __('Photo Gallery') }}</a></li>
                    </ul>
                </div>
                <a href="{{ lroute('newsletter') }}" class="btn btn-primary w-full">{{ __('Get the newsletter') }}</a>
            </div>
        </div>
    </div>
</header>

{{-- ── Breaking News command bar ─────────────────────────────────── --}}
<div x-data x-cloak x-show="$store.live.alerts.length" class="border-b border-[var(--rule)] bg-[var(--surface)] no-print" aria-live="polite">
    <div class="wrap flex items-stretch">
        <a href="{{ lroute('breaking') }}" class="flex flex-none items-center gap-2 bg-[var(--color-alert-600)] px-3 sm:px-4 text-[0.6875rem] font-extrabold uppercase tracking-[0.12em] text-white">
            <span class="live-dot"></span><span class="hidden xs:inline">{{ __('Breaking') }}</span>
        </a>
        <div class="ticker relative flex-1 overflow-hidden py-2.5 pl-4" :style="`--ticker-duration: ${Math.max(25, $store.live.alerts.length * 14)}s`">
            <div class="pointer-events-none absolute inset-y-0 right-0 w-12 bg-gradient-to-l from-[var(--surface)] z-10"></div>
            <div class="ticker-track text-sm font-semibold">
                <template x-for="copy in [0, 1]" :key="copy">
                    <div class="flex gap-12" :aria-hidden="copy === 1">
                        <template x-for="a in $store.live.alerts" :key="a.id + '-' + copy">
                            <a :href="a.url || '{{ lroute('breaking') }}'" class="flex items-center gap-3 whitespace-nowrap hover:underline" :tabindex="copy === 1 ? -1 : 0">
                                <span class="h-1.5 w-1.5 rotate-45 bg-[var(--color-alert-600)]"></span>
                                <span x-text="a.headline"></span>
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<main id="main" class="flex-1">
    @yield('content')
</main>

{{-- ── Footer ────────────────────────────────────────────────────── --}}
<footer class="mt-16 bg-[var(--color-forest-950)] text-white/80 no-print">
    @if ($features['newsletter'] ?? true)
        <div class="border-b border-white/10">
            <div class="wrap grid gap-6 py-10 md:grid-cols-2 md:items-center">
                <div>
                    <p class="font-serif text-2xl font-semibold text-white">{{ __('The day’s essential stories, in your inbox.') }}</p>
                    <p class="mt-1 text-sm text-white/60">{{ __('A free morning briefing from the Doogsan Media newsroom.') }}</p>
                </div>
                <form method="post" action="{{ lroute('newsletter.subscribe') }}" class="flex min-w-0 flex-col gap-2 xs:flex-row">
                    @csrf
                    <input type="hidden" name="source" value="footer">
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                    <label class="sr-only" for="footer-email">{{ __('Email address') }}</label>
                    <input id="footer-email" type="email" name="email" required placeholder="{{ __('Your email address') }}" class="min-w-0 flex-1 rounded-sm border-0 bg-white/10 px-4 py-3 text-white placeholder:text-white/40 focus:ring-2 focus:ring-[var(--color-lime-400)]">
                    <button class="btn bg-[var(--color-lime-400)] text-[var(--color-forest-950)] hover:bg-[var(--color-lime-300)]">{{ __('Subscribe') }}</button>
                </form>
            </div>
        </div>
    @endif
    <div class="wrap grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <img src="{{ brand_logo('dark') }}" alt="Doogsan Media" class="h-11 w-auto" loading="lazy">
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/60">{{ lsetting('footer_note', __('Doogsan Media is an independent news organisation reporting from Puntland, across Somalia and beyond, in English and Somali.')) }}</p>
            <dl class="mt-5 space-y-1.5 text-sm">
                <div class="flex gap-2"><dt class="text-white/40">{{ __('Email') }}</dt><dd><a href="mailto:{{ setting('contact_email', config('doogsan.contact_email')) }}" class="hover:text-[var(--color-lime-400)]">{{ setting('contact_email', config('doogsan.contact_email')) }}</a></dd></div>
                @if (setting('contact_phone'))<div class="flex gap-2"><dt class="text-white/40">{{ __('Phone') }}</dt><dd>{{ setting('contact_phone') }}</dd></div>@endif
                @if (setting('contact_address'))<div class="flex gap-2"><dt class="text-white/40">{{ __('Address') }}</dt><dd>{{ setting('contact_address') }}</dd></div>@endif
            </dl>
            @if ($social)
                <div class="mt-5 flex gap-3">
                    @foreach ($social as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}" class="grid h-9 w-9 place-items-center rounded-full bg-white/10 hover:bg-[var(--color-lime-400)] hover:text-[var(--color-forest-950)]"><x-social-icon :network="$network" class="h-4 w-4" /></a>
                    @endforeach
                </div>
            @endif
        </div>
        @php
            $cols = [
                'footer_news' => __('News'),
                'footer_journalism' => __('Journalism'),
                'footer_company' => 'Doogsan Media',
            ];
        @endphp
        @foreach ($cols as $loc => $heading)
            <div class="lg:col-span-2">
                <p class="mb-3 text-xs font-extrabold uppercase tracking-[0.12em] text-[var(--color-lime-400)]">{{ $heading }}</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($menus[$loc] ?? [] as $item)
                        <li><a href="{{ $item['href'] }}" class="hover:text-white">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <div class="lg:col-span-2">
            <p class="mb-3 text-xs font-extrabold uppercase tracking-[0.12em] text-[var(--color-lime-400)]">{{ __('Policies') }}</p>
            <ul class="space-y-2 text-sm">
                @foreach (($menus['footer_legal'] ?? []) ?: $footerPages as $item)
                    <li><a href="{{ $item['href'] }}" class="hover:text-white">{{ $item['label'] }}</a></li>
                @endforeach
                <li><a href="{{ lroute('rss') }}" class="hover:text-white">RSS</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="wrap flex flex-col gap-2 py-5 text-xs text-white/40 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ local_time(now())->year }} Doogsan Media. {{ __('All rights reserved.') }}</p>
            <p class="order-last sm:order-none">
                {{ __('Designed & Developed by') }}
                <a href="https://somalidigital.so" target="_blank" rel="noopener" class="font-bold text-white/70 underline decoration-[var(--color-lime-400)]/60 decoration-2 underline-offset-4 hover:text-[var(--color-lime-400)] hover:decoration-[var(--color-lime-400)]">Somali Digital</a>
            </p>
            <p class="flex items-center gap-4">
                <a href="{{ lroute('home', [], $other) }}" class="hover:text-white" lang="{{ $other }}">{{ editions()[$other]['native'] }} {{ $other === 'so' ? 'edition' : '' }}</a>
                <a href="{{ lroute('archive') }}" class="hover:text-white">{{ __('Archive') }}</a>
                <a href="{{ lroute('tip') }}" class="hover:text-white">{{ __('Submit a tip') }}</a>
            </p>
        </div>
    </div>
</footer>

{{-- Image lightbox --}}
<div x-data="lightbox" x-cloak x-show="open" @keydown.escape.window="open = false" class="fixed inset-0 z-[60] flex flex-col bg-black/95 p-4" role="dialog" aria-modal="true">
    <button @click="open = false" class="ml-auto p-2 text-white" aria-label="{{ __('Close') }}"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
    <div class="flex flex-1 items-center justify-center" @click.self="open = false"><img :src="src" alt="" class="max-h-[80vh] max-w-full object-contain"></div>
    <p class="mx-auto max-w-3xl pb-4 text-center text-sm text-white/70" x-text="caption"></p>
</div>

@stack('scripts')
</body>
</html>
