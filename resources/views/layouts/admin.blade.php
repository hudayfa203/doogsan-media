@php
    $u = auth()->user();
    $nav = [
        'Newsroom' => [
            ['admin.dashboard', 'Executive Overview', 'grid', null, 'admin.dashboard'],
            [['admin.newsroom.show', 'so'], 'Somali Newsroom', 'file', 'articles.view', 'so'],
            [['admin.newsroom.show', 'en'], 'English Newsroom', 'file', 'articles.view', 'en'],
            ['admin.articles.all', 'All Articles', 'list', 'articles.view', 'admin.articles.all'],
            ['admin.articles.translations', 'Translations', 'translate', 'translations.manage', 'admin.articles.translations'],
            ['admin.breaking.index', 'Breaking News', 'bolt', 'breaking.manage', 'admin.breaking.*'],
            ['admin.live.index', 'Live Coverage', 'radio', 'live.manage', 'admin.live.*'],
            ['admin.homepage.edit', 'Homepage', 'layout', 'homepage.manage', 'admin.homepage.*'],
        ],
        'Content' => [
            ['admin.media.index', 'Media Library', 'image', 'media.manage', 'admin.media.*'],
            ['admin.broadcast.index', 'Live TV Studio', 'tv', 'video.manage', 'admin.broadcast.*'],
            ['admin.videos.index', 'Video & Podcasts', 'video', 'video.manage', 'admin.videos.*'],
            ['admin.authors.index', 'Journalists', 'users', 'authors.manage', 'admin.authors.*'],
            ['admin.categories.index', 'Sections & Tags', 'tag', 'taxonomy.manage', 'admin.categories.*|admin.tags.*'],
            ['admin.pages.index', 'Pages', 'pages', 'pages.manage', 'admin.pages.*'],
            ['admin.menus.edit', 'Navigation', 'menu', 'menus.manage', 'admin.menus.*'],
        ],
        'Audience & Revenue' => [
            ['admin.analytics', 'Analytics', 'chart', 'analytics.view', 'admin.analytics'],
            ['admin.seo.index', 'SEO Center', 'search', 'seo.manage', 'admin.seo.*'],
            ['admin.ads.index', 'Advertising', 'megaphone', 'ads.manage', 'admin.ads.*'],
            ['admin.newsletter.index', 'Newsletter', 'mail', 'newsletter.manage', 'admin.newsletter.*'],
            ['admin.inbox.index', 'Messages & Tips', 'inbox', 'inbox.manage', 'admin.inbox.*'],
        ],
        'Administration' => [
            ['admin.users.index', 'Users', 'users', 'users.manage', 'admin.users.*'],
            ['admin.roles.index', 'Roles & Permissions', 'key', 'users.manage', 'admin.roles.*'],
            ['admin.settings', 'Site Settings', 'cog', 'settings.manage', 'admin.settings*'],
            ['admin.audit', 'Audit Log', 'shield', 'audit.view', 'admin.audit'],
            ['admin.system', 'System Health', 'pulse', 'system.view', 'admin.system'],
            ['admin.backups', 'Backups', 'database', 'backups.manage', 'admin.backups*'],
        ],
    ];
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
    $adminCfg = ['reverb' => $reverb, 'user' => $u->name];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Newsroom') · Doogsan Newsroom</title>
    <link rel="icon" href="{{ asset('images/brand/favicon-32.png') }}" type="image/png">
    <script>window.DoogsanAdmin = {!! json_encode($adminCfg, JSON_HEX_TAG) !!};</script>
    @vite(['resources/css/admin.css', 'resources/js/admin/app.js'])
</head>
<body class="min-h-screen" x-data="{ nav: false }">

{{-- Sidebar --}}
<aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-[var(--color-ink-700)] bg-[var(--color-ink-900)] transition lg:translate-x-0" :class="nav && '!translate-x-0'">
    <div class="flex h-16 items-center gap-3 border-b border-[var(--color-ink-700)] px-5">
        <img src="{{ asset('images/brand/doogsan-logo-dark.png') }}" alt="Doogsan Media" class="h-8 w-auto">
        <span class="rounded bg-[var(--color-ink-700)] px-1.5 py-0.5 text-[0.625rem] font-extrabold tracking-wider text-[var(--color-signal)]">NEWSROOM</span>
    </div>
    <nav class="scroll-thin flex-1 overflow-y-auto px-3 pb-6" aria-label="Newsroom">
        @foreach ($nav as $group => $items)
            @php $visible = collect($items)->filter(fn ($i) => ! $i[3] || $u->hasPermission($i[3])); @endphp
            @if ($visible->isNotEmpty())
                <p class="nav-group">{{ $group }}</p>
                @foreach ($visible as [$route, $label, $icon, $perm, $match])
                    @php
                        $isEdition = is_array($route);
                        if ($isEdition && ! $u->worksIn($route[1])) { continue; }
                        $href = $isEdition ? route($route[0], $route[1]) : route($route);
                        $active = $isEdition ? (request()->route('edition') === $match && request()->routeIs('admin.newsroom.*', 'admin.articles.index', 'admin.articles.create', 'admin.articles.edit')) : request()->routeIs(...explode('|', $match));
                    @endphp
                    <a href="{{ $href }}" class="nav-item {{ $active ? 'is-active' : '' }}">
                        <x-admin.icon :name="$icon" /> {{ $label }}
                    </a>
                @endforeach
            @endif
        @endforeach
    </nav>
    <div class="border-t border-[var(--color-ink-700)] p-3">
        <a href="{{ url('/en') }}" target="_blank" class="nav-item"><x-admin.icon name="external" /> View English site</a>
        <a href="{{ url('/so') }}" target="_blank" class="nav-item"><x-admin.icon name="external" /> View Somali site</a>
    </div>
</aside>
<div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-black/60 lg:hidden"></div>

<div class="lg:pl-64">
    {{-- Top bar --}}
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-[var(--color-ink-700)] bg-[var(--color-ink-950)]/90 px-4 backdrop-blur md:px-6">
        <button @click="nav = true" class="btn btn-ghost !px-2 lg:hidden" aria-label="Open navigation"><x-admin.icon name="menu" class="h-5 w-5" /></button>
        <form action="{{ route('admin.articles.all') }}" class="hidden max-w-md flex-1 md:block">
            <div class="relative">
                <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-ink-500)]" />
                <input name="q" value="{{ request('q') }}" placeholder="Search stories by headline…" class="input !pl-9 !bg-[var(--color-ink-900)]">
            </div>
        </form>
        <div class="ml-auto flex items-center gap-3">
            <span class="hidden items-center gap-2 text-xs font-bold text-[var(--color-ink-400)] sm:flex" title="Real-time newsroom connection">
                <span class="h-2 w-2 rounded-full bg-[var(--color-ink-500)] [html[data-socket=on]_&]:bg-[var(--color-signal)]"></span>
                <span class="[html[data-socket=on]_&]:hidden">Polling</span><span class="hidden [html[data-socket=on]_&]:inline">Real-time</span>
            </span>
            <span x-data="{ t: '' }" x-init="const f = () => t = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Mogadishu', hour: '2-digit', minute: '2-digit', weekday: 'short' }).format(new Date()); f(); setInterval(f, 15000)" class="num hidden text-xs text-[var(--color-ink-300)] md:inline"><span x-text="t"></span> EAT</span>
            @can('articles.create')
                <div x-data="{ o: false }" class="relative">
                    <button @click="o = !o" class="btn btn-primary"><x-admin.icon name="plus" /> <span class="hidden sm:inline">New story</span></button>
                    <div x-show="o" x-cloak @click.outside="o = false" class="absolute right-0 mt-2 w-56 rounded-lg border border-[var(--color-ink-700)] bg-[var(--color-ink-900)] p-1.5 shadow-2xl">
                        @foreach ($u->newsroomEditions() as $l)
                            <a href="{{ route('admin.articles.create', $l) }}" class="nav-item">{{ $l === 'so' ? 'Sheeko Soomaali ah' : 'English story' }} <span class="ml-auto text-[0.625rem] font-extrabold">{{ strtoupper($l) }}</span></a>
                        @endforeach
                    </div>
                </div>
            @endcan
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-2 rounded-lg p-1 hover:bg-[var(--color-ink-800)]">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-ink-700)] text-xs font-extrabold text-[var(--color-signal)]">{{ $u->initials() }}</span>
                    <span class="hidden text-left leading-tight md:block"><span class="block text-xs font-bold">{{ $u->name }}</span><span class="block text-[0.6875rem] text-[var(--color-ink-400)]">{{ $u->role?->name }}</span></span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-56 rounded-lg border border-[var(--color-ink-700)] bg-[var(--color-ink-900)] p-1.5 shadow-2xl">
                    <a href="{{ route('admin.security') }}" class="nav-item"><x-admin.icon name="shield" /> Account security</a>
                    <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="nav-item w-full"><x-admin.icon name="logout" /> Sign out</button></form>
                </div>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 md:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-5 flex items-center gap-3 rounded-lg border border-[var(--color-signal-dim)]/50 bg-[color-mix(in_oklab,var(--color-signal)_10%,transparent)] px-4 py-3 text-sm font-semibold text-[var(--color-signal)]" role="status">✓ {{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="mb-5 rounded-lg border border-[var(--color-amber)]/50 bg-[color-mix(in_oklab,var(--color-amber)_10%,transparent)] px-4 py-3 text-sm font-semibold text-[#ffd98a]">{{ session('warning') }}</div>
        @endif
        @if (session('error') || $errors->any())
            <div class="mb-5 rounded-lg border border-[var(--color-alarm)]/50 bg-[color-mix(in_oklab,var(--color-alarm)_10%,transparent)] px-4 py-3 text-sm text-[#ffc9c4]" role="alert">
                @if (session('error'))<p class="font-semibold">{{ session('error') }}</p>@endif
                <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>

{{-- Toasts --}}
<div x-data class="fixed bottom-4 right-4 z-[70] flex w-80 flex-col gap-2">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div x-transition class="rounded-lg border px-4 py-3 text-sm font-semibold shadow-2xl"
             :class="{ 'border-[var(--color-signal-dim)] bg-[var(--color-ink-850)] text-[var(--color-signal)]': t.kind === 'ok', 'border-[var(--color-alarm)] bg-[var(--color-ink-850)] text-[#ffc9c4]': t.kind === 'err', 'border-[var(--color-ink-600)] bg-[var(--color-ink-850)] text-[var(--color-ink-100)]': t.kind === 'info' }" x-text="t.text"></div>
    </template>
</div>

{{-- Media picker modal --}}
<div x-data x-cloak x-show="$store.media.open" @keydown.escape.window="$store.media.open = false" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4">
    <div @click.outside="$store.media.open = false" class="panel flex max-h-[88vh] w-full max-w-5xl flex-col !bg-[var(--color-ink-900)]">
        <div class="panel-head">
            <h2>Media library</h2>
            <div class="flex items-center gap-2">
                <input x-model="$store.media.q" @input.debounce.350ms="$store.media.load()" placeholder="Search title, credit…" class="input !w-56 !py-1.5">
                <label class="btn btn-primary btn-sm cursor-pointer"><input type="file" class="hidden" multiple accept="image/*,video/*,audio/*,.pdf" @change="$store.media.upload($event.target.files)">
                    <span x-text="$store.media.uploading ? 'Uploading…' : 'Upload'"></span></label>
                <button @click="$store.media.open = false" class="btn btn-ghost btn-sm">Close</button>
            </div>
        </div>
        <div class="scroll-thin grid flex-1 grid-cols-3 gap-3 overflow-y-auto p-4 sm:grid-cols-4 md:grid-cols-6">
            <template x-for="m in $store.media.items" :key="m.id">
                <button type="button" @click="$store.media.toggle(m)" class="group relative aspect-square overflow-hidden rounded-lg border-2 bg-[var(--color-ink-850)]"
                        :class="$store.media.selected.find(s => s.id === m.id) ? 'border-[var(--color-signal)]' : 'border-transparent hover:border-[var(--color-ink-500)]'">
                    <template x-if="m.type === 'image'"><img :src="m.url" class="h-full w-full object-cover" alt=""></template>
                    <span x-show="m.type !== 'image'" class="grid h-full place-items-center text-xs font-bold uppercase text-[var(--color-ink-400)]" x-text="m.type"></span>
                    <span class="absolute inset-x-0 bottom-0 truncate bg-black/70 px-1.5 py-1 text-left text-[0.625rem]" x-text="m.title"></span>
                </button>
            </template>
            <p x-show="!$store.media.loading && !$store.media.items.length" class="col-span-full py-10 text-center text-sm text-[var(--color-ink-400)]">No files yet — upload one.</p>
        </div>
        <div class="flex items-center justify-between border-t border-[var(--color-ink-700)] px-4 py-3 text-xs">
            <div class="flex gap-2">
                <button class="btn btn-secondary btn-sm" :disabled="$store.media.page <= 1" @click="$store.media.load($store.media.page - 1)">Prev</button>
                <button class="btn btn-secondary btn-sm" :disabled="$store.media.page >= $store.media.last" @click="$store.media.load($store.media.page + 1)">Next</button>
            </div>
            <button x-show="$store.media.multiple" class="btn btn-primary btn-sm" @click="$store.media.confirmMany()">Add <span x-text="$store.media.selected.length"></span> selected</button>
        </div>
    </div>
</div>
@stack('scripts')
</body>
</html>
