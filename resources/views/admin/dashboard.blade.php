@extends('layouts.admin')
@section('title', 'Command Center')

@section('content')
@php
    $tz = config('doogsan.timezone');
    $dayLabels = collect(array_keys($daily))->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'))->values();
    $views = $summary['views'];
    $hasTraffic = $views > 0;
    $byLocale = $summary['by_locale'];
@endphp

<x-admin.page-header eyebrow="Doogsan Newsroom" title="Command Center" :subtitle="'Good '.(local_time(now())->hour < 12 ? 'morning' : (local_time(now())->hour < 17 ? 'afternoon' : 'evening')).', '.explode(' ', auth()->user()->name)[0].' — '.news_date(now(), false)">
    <form class="flex items-center gap-2 text-xs" method="get">
        <input type="date" name="from" value="{{ $from }}" class="input !w-auto !py-1.5">
        <span class="text-[var(--color-ink-400)]">→</span>
        <input type="date" name="to" value="{{ $to }}" class="input !w-auto !py-1.5">
        <button class="btn btn-secondary btn-sm">Apply</button>
        @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $d => $l)
            <a href="?from={{ now()->subDays($d - 1)->setTimezone($tz)->toDateString() }}&to={{ now()->setTimezone($tz)->toDateString() }}" class="btn btn-ghost btn-sm">{{ $l }}</a>
        @endforeach
    </form>
</x-admin.page-header>

{{-- Operational alerts --}}
@if ($opsAlerts)
    <div class="mb-6 grid gap-2 md:grid-cols-2">
        @foreach ($opsAlerts as $al)
            <a href="{{ $al['url'] }}" class="flex items-start gap-3 rounded-lg border px-4 py-2.5 text-sm hover:bg-[var(--color-ink-900)] {{ $al['level'] === 'fail' ? 'border-[var(--color-alarm)]/60 text-[#ffc9c4]' : ($al['level'] === 'info' ? 'border-[var(--color-sky)]/40 text-[#a7d8ff]' : 'border-[var(--color-amber)]/50 text-[#ffd98a]') }}">
                <span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-current"></span><span>{{ $al['text'] }}</span>
            </a>
        @endforeach
    </div>
@endif

{{-- KPI strip --}}
<div x-data="metrics({ live_visitors: {{ $stats['live_visitors'] }}, pending: {{ $stats['pending'] }}, breaking: {{ $stats['breaking'] }}, inbox: 0 })" class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
    @php
        $kpis = [
            ['Published', $stats['published'], 'Live story versions', route('admin.articles.all', ['status' => 'published']), null],
            ['Today', $stats['today'], 'EN '.($stats['today_by_locale']['en'] ?? 0).' · SO '.($stats['today_by_locale']['so'] ?? 0), null, null],
            ['Pending review', null, 'Submitted + editing', route('admin.articles.all', ['status' => 'submitted']), 'pending'],
            ['Drafts', $stats['drafts'], 'In progress', route('admin.articles.all', ['status' => 'draft']), null],
            ['Scheduled', $stats['scheduled'], 'Queued to publish', route('admin.articles.all', ['status' => 'scheduled']), null],
            ['Breaking', null, 'Active alerts', route('admin.breaking.index'), 'breaking'],
            ['Live visitors', null, 'Last 5 minutes', route('admin.analytics'), 'live_visitors'],
            ['Subscribers', $stats['subscribers'], '+'.$stats['subscribers_new'].' this period', route('admin.newsletter.index'), null],
        ];
    @endphp
    @foreach ($kpis as [$label, $value, $sub, $href, $live])
        <a @if ($href) href="{{ $href }}" @endif class="panel block p-4 {{ $href ? 'hover:border-[var(--color-ink-500)]' : '' }}">
            <p class="eyebrow">{{ $label }}</p>
            <p class="num mt-2 text-3xl font-semibold {{ $label === 'Breaking' ? 'text-[var(--color-alarm)]' : 'text-white' }}" @if ($live) x-text="m.{{ $live }}" @endif>{{ $live ? $stats[$live] : number_format($value) }}</p>
            <p class="mt-1 text-[0.6875rem] text-[var(--color-ink-400)]">{{ $sub }}</p>
        </a>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    {{-- Traffic --}}
    <section class="panel xl:col-span-2">
        <div class="panel-head">
            <h2>Audience — views by edition</h2>
            <div class="flex items-center gap-4 text-xs">
                <span class="num text-white">{{ number_format($views) }} <span class="text-[var(--color-ink-400)]">views</span></span>
                <span class="num text-white">{{ number_format($summary['visitors']) }} <span class="text-[var(--color-ink-400)]">unique readers</span></span>
            </div>
        </div>
        <div class="panel-body">
            @if ($hasTraffic)
                @php
                    $trafficChart = [
                        'type' => 'line',
                        'data' => ['labels' => $dayLabels, 'datasets' => [
                            ['label' => 'English', 'data' => collect($daily)->pluck('en')->values(), 'borderColor' => '#a5ea4f', 'backgroundColor' => 'rgba(165,234,79,.12)', 'fill' => true, 'tension' => .35, 'pointRadius' => 0, 'borderWidth' => 2],
                            ['label' => 'Somali', 'data' => collect($daily)->pluck('so')->values(), 'borderColor' => '#5cb8ff', 'backgroundColor' => 'rgba(92,184,255,.10)', 'fill' => true, 'tension' => .35, 'pointRadius' => 0, 'borderWidth' => 2],
                        ]],
                        'options' => ['maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false],
                            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 10, 'boxHeight' => 10]]],
                            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['grid' => ['display' => false]]]],
                    ];
                @endphp
                <div class="h-64" x-data="chart(@js($trafficChart))"><canvas x-ref="canvas"></canvas></div>
            @else
                <div class="grid h-64 place-items-center rounded-lg border border-dashed border-[var(--color-ink-600)] text-center">
                    <div class="max-w-sm px-6">
                        <x-admin.icon name="chart" class="mx-auto h-8 w-8 text-[var(--color-ink-500)]" />
                        <p class="mt-3 font-bold">No reader traffic recorded in this period</p>
                        <p class="mt-1 text-xs text-[var(--color-ink-400)]">Charts fill automatically from first-party page views once readers visit the site. Nothing here is simulated.</p>
                    </div>
                </div>
            @endif
            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach (['en' => 'English', 'so' => 'Somali'] as $l => $name)
                    <div class="rounded-lg bg-[var(--color-ink-850)] p-3">
                        <p class="eyebrow">{{ $name }} views</p>
                        <p class="num mt-1 text-xl text-white">{{ number_format($byLocale[$l] ?? 0) }}</p>
                        <p class="text-[0.6875rem] text-[var(--color-ink-400)]">{{ $views ? round(($byLocale[$l] ?? 0) / $views * 100) : 0 }}% of total</p>
                    </div>
                @endforeach
                <div class="rounded-lg bg-[var(--color-ink-850)] p-3">
                    <p class="eyebrow">Top source</p>
                    <p class="mt-1 text-xl font-bold capitalize text-white">{{ array_key_first($summary['by_source']) ?? '—' }}</p>
                    <p class="text-[0.6875rem] text-[var(--color-ink-400)]">{{ collect($summary['by_source'])->first() ?? 0 }} views</p>
                </div>
                <div class="rounded-lg bg-[var(--color-ink-850)] p-3">
                    <p class="eyebrow">Video plays</p>
                    <p class="num mt-1 text-xl text-white">{{ number_format($stats['video_views_period']) }}</p>
                    <p class="text-[0.6875rem] text-[var(--color-ink-400)]">{{ number_format($stats['video_plays']) }} all-time</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Live operations --}}
    <section class="panel">
        <div class="panel-head"><h2>Live operations</h2><a href="{{ route('admin.live.index') }}" class="text-xs font-bold text-[var(--color-ink-400)] hover:text-white">All →</a></div>
        <div class="panel-body space-y-3">
            @forelse ($liveEvents as $ev)
                <a href="{{ route('admin.live.desk', $ev) }}" class="flex items-center gap-3 rounded-lg border border-[var(--color-alarm)]/40 bg-[color-mix(in_oklab,var(--color-alarm)_8%,transparent)] p-3 hover:border-[var(--color-alarm)]">
                    <span class="live-dot text-[var(--color-alarm)]"></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold">{{ $ev->title }}</p>
                        <p class="text-[0.6875rem] text-[var(--color-ink-400)]">{{ strtoupper($ev->locale) }} · {{ $ev->all_updates_count }} updates · started {{ $ev->starts_at?->diffForHumans() }}</p>
                    </div>
                    <span class="btn btn-secondary btn-sm">Desk</span>
                </a>
            @empty
                <p class="text-sm text-[var(--color-ink-400)]">No live coverage running.</p>
            @endforelse
            @can('breaking.manage')
                <a href="{{ route('admin.breaking.index') }}" class="btn btn-danger w-full"><x-admin.icon name="bolt" /> Push a breaking alert</a>
            @endcan
            @can('live.manage')
                <a href="{{ route('admin.live.index') }}" class="btn btn-secondary w-full"><x-admin.icon name="radio" /> Open live coverage</a>
            @endcan
        </div>
        <div class="border-t border-[var(--color-ink-700)] p-4">
            <p class="eyebrow mb-3">Scheduled publications</p>
            <ul class="space-y-2 text-sm">
                @forelse ($scheduled as $s)
                    <li class="flex items-center gap-3"><span class="num w-24 flex-none text-xs text-[var(--color-ink-300)]">{{ local_time($s->scheduled_at)->format('d M H:i') }}</span><a href="{{ story_edit_url($s->article_id, $s->locale) }}" class="truncate hover:text-[var(--color-signal)]">{{ $s->title }}</a><span class="ml-auto text-[0.625rem] font-bold uppercase text-[var(--color-ink-400)]">{{ $s->locale }}</span></li>
                @empty
                    <li class="text-[var(--color-ink-400)]">Nothing scheduled.</li>
                @endforelse
            </ul>
        </div>
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    {{-- Workflow --}}
    <section class="panel xl:col-span-2">
        <div class="panel-head"><h2>Editorial pipeline</h2><a href="{{ route('admin.articles.all') }}" class="text-xs font-bold text-[var(--color-ink-400)] hover:text-white">All articles →</a></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Story</th><th>Ed.</th><th>Status</th><th>Byline</th><th>Assigned</th><th>Updated</th></tr></thead>
                <tbody>
                    @forelse ($pipeline as $p)
                        <tr>
                            <td class="max-w-xs"><a href="{{ story_edit_url($p->article_id, $p->locale) }}" class="font-semibold hover:text-[var(--color-signal)]">{{ \Illuminate\Support\Str::limit($p->title, 60) }}</a>
                                @if ($p->locked_by)<p class="text-[0.6875rem] text-[#ffd98a]">🔒 {{ $p->locker?->name }} editing</p>@endif</td>
                            <td><span class="text-[0.625rem] font-extrabold uppercase">{{ $p->locale }}</span></td>
                            <td><x-admin.status :status="$p->status" /></td>
                            <td class="text-[var(--color-ink-300)]">{{ $p->article->author?->name ?? '—' }}</td>
                            <td class="text-[var(--color-ink-300)]">{{ $p->article->assignee?->name ?? '—' }}</td>
                            <td class="num text-xs text-[var(--color-ink-400)]">{{ $p->updated_at->diffForHumans(null, true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-[var(--color-ink-400)]">The review queue is clear.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($deadlines->isNotEmpty())
            <div class="border-t border-[var(--color-ink-700)] p-4">
                <p class="eyebrow mb-3">Assignment deadlines</p>
                <div class="grid gap-2 md:grid-cols-2">
                    @foreach ($deadlines as $d)
                        <a href="{{ story_edit_url($d) }}" class="flex items-center gap-3 rounded-lg bg-[var(--color-ink-850)] px-3 py-2 text-sm hover:bg-[var(--color-ink-800)]">
                            <x-admin.icon name="clock" class="{{ $d->deadline_at->isPast() ? 'text-[var(--color-alarm)]' : 'text-[var(--color-amber)]' }}" />
                            <span class="truncate">{{ $d->displayTitle() }}</span>
                            <span class="num ml-auto flex-none text-xs {{ $d->deadline_at->isPast() ? 'text-[var(--color-alarm)]' : 'text-[var(--color-ink-300)]' }}">{{ local_time($d->deadline_at)->format('d M H:i') }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- Activity --}}
    <section class="panel" x-data="activityFeed([])">
        <div class="panel-head"><h2>Newsroom activity</h2>@can('audit.view')<a href="{{ route('admin.audit') }}" class="text-xs font-bold text-[var(--color-ink-400)] hover:text-white">Audit log →</a>@endcan</div>
        <ol class="scroll-thin max-h-[26rem] space-y-3 overflow-y-auto p-4 text-sm">
            <template x-for="(a, i) in items" :key="i">
                <li class="flex gap-3"><span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-[var(--color-signal)]"></span><p><strong x-text="a.user"></strong> <span x-text="a.message"></span> <span class="text-xs text-[var(--color-ink-500)]">· just now</span></p></li>
            </template>
            @foreach ($activity as $log)
                <li class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 flex-none rounded-full {{ str_starts_with($log->action, 'article.published') || str_starts_with($log->action, 'breaking') ? 'bg-[var(--color-signal)]' : (str_starts_with($log->action, 'auth.failed') ? 'bg-[var(--color-alarm)]' : 'bg-[var(--color-ink-500)]') }}"></span>
                    <p class="min-w-0"><strong>{{ $log->user?->name ?? 'System' }}</strong> <span class="text-[var(--color-ink-200)]">{{ \Illuminate\Support\Str::limit($log->description, 90) }}</span> <span class="text-xs text-[var(--color-ink-500)]">· {{ $log->created_at->diffForHumans(null, true) }}</span></p>
                </li>
            @endforeach
        </ol>
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="panel">
        <div class="panel-head"><h2>Most read</h2><span class="text-xs text-[var(--color-ink-400)]">this period</span></div>
        <ol class="divide-y divide-[var(--color-ink-800)]">
            @forelse ($topArticles as $i => $t)
                <li class="flex gap-3 px-4 py-3">
                    <span class="num w-5 text-lg text-[var(--color-signal)]">{{ $i + 1 }}</span>
                    <div class="min-w-0 flex-1"><a href="{{ $t->url() }}" target="_blank" class="line-clamp-2 text-sm font-semibold hover:text-[var(--color-signal)]">{{ $t->title }}</a>
                        <p class="text-[0.6875rem] text-[var(--color-ink-400)]">{{ strtoupper($t->locale) }} · {{ $t->article->author?->name }}</p></div>
                    <span class="num text-sm text-white">{{ number_format($t->period_views) }}</span>
                </li>
            @empty
                <li class="px-4 py-6 text-sm text-[var(--color-ink-400)]">Rankings appear once readers start visiting.</li>
            @endforelse
        </ol>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Most active sections</h2><span class="text-xs text-[var(--color-ink-400)]">stories published</span></div>
        <div class="panel-body space-y-3">
            @php $max = max(1, $categoryActivity->max() ?? 1); @endphp
            @forelse ($categoryActivity as $catId => $count)
                <div>
                    <div class="mb-1 flex justify-between text-xs"><span class="font-semibold">{{ $categories[$catId]?->nameFor('en') }}</span><span class="num text-[var(--color-ink-300)]">{{ $count }}</span></div>
                    <div class="h-2 rounded-full bg-[var(--color-ink-800)]"><div class="h-2 rounded-full" style="width: {{ $count / $max * 100 }}%; background: {{ $categories[$catId]?->color ?? '#a5ea4f' }}"></div></div>
                </div>
            @empty
                <p class="text-sm text-[var(--color-ink-400)]">No stories published in this period.</p>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Top journalists</h2><span class="text-xs text-[var(--color-ink-400)]">by output</span></div>
        <ol class="divide-y divide-[var(--color-ink-800)]">
            @forelse ($journalists as $j)
                <li class="flex items-center gap-3 px-4 py-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--color-ink-700)] text-[0.6875rem] font-extrabold text-[var(--color-signal)]">{{ collect(explode(' ', $j->name))->take(2)->map(fn ($p) => $p[0])->implode('') }}</span>
                    <span class="flex-1 text-sm font-semibold">{{ $j->name }}</span>
                    <span class="num text-xs text-[var(--color-ink-300)]">{{ $j->stories }} stories · {{ number_format($j->views) }} reads</span>
                </li>
            @empty
                <li class="px-4 py-6 text-sm text-[var(--color-ink-400)]">No published stories in this period.</li>
            @endforelse
        </ol>
    </section>
</div>
@endsection
