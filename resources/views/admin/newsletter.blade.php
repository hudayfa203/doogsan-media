@extends('layouts.admin')
@section('title', 'Newsletter')

@section('content')
@php
    $growthChart = [
        'type' => 'bar',
        'data' => ['labels' => $growth->keys()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'))->values(), 'datasets' => [
            ['label' => 'New confirmed subscribers', 'data' => $growth->values(), 'backgroundColor' => '#6fae27', 'borderRadius' => 3],
        ]],
        'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['grid' => ['display' => false]]]],
    ];
@endphp
<x-admin.page-header eyebrow="Audience" title="Newsletter" subtitle="Double opt-in lists per edition. Somali subscribers receive only Somali issues; English subscribers only English issues.">
    <a href="{{ route('admin.newsletter.export') }}" class="btn btn-secondary">Export active (CSV)</a>
</x-admin.page-header>

<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
    <div class="panel p-4"><p class="eyebrow">Active</p><p class="num mt-1 text-2xl font-bold text-white">{{ number_format($stats['active']) }}</p></div>
    @foreach (editions() as $l => $e)
        <div class="panel p-4"><p class="eyebrow">{{ $e['name'] }} list</p><p class="num mt-1 text-2xl font-bold text-white">{{ number_format($stats['by_locale'][$l] ?? 0) }}</p></div>
    @endforeach
    <div class="panel p-4"><p class="eyebrow">Awaiting confirmation</p><p class="num mt-1 text-2xl font-bold text-white">{{ number_format($stats['pending']) }}</p></div>
    <div class="panel p-4"><p class="eyebrow">Unsubscribed</p><p class="num mt-1 text-2xl font-bold text-white">{{ number_format($stats['unsubscribed']) }}</p></div>
</div>

<div class="grid gap-6 xl:grid-cols-[28rem_1fr]">
    <section class="panel" x-data="{ ed: 'en' }">
        <div class="panel-head"><h2>Draft an issue</h2>
            <div class="flex gap-1">@foreach (editions() as $l => $e)<button type="button" @click="ed = '{{ $l }}'" class="btn btn-sm" :class="ed === '{{ $l }}' ? 'btn-secondary' : 'btn-ghost'">{{ $e['name'] }}</button>@endforeach</div>
        </div>
        @foreach (editions() as $l => $e)
            <form x-show="ed === '{{ $l }}'" @if (! $loop->first) x-cloak @endif method="post" action="{{ route('admin.newsletter.store') }}" class="panel-body space-y-3" lang="{{ $l }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $l }}">
                <div><label class="label">Subject</label><input name="subject" required maxlength="190" class="input" placeholder="{{ $l === 'so' ? 'Wararka muhiimka ah ee maanta' : 'Today’s essential stories' }}"></div>
                <div><label class="label">Introduction</label><textarea name="intro" rows="3" maxlength="2000" class="textarea"></textarea></div>
                <div x-data="articlePicker([], { max: 12, locale: '{{ $l }}', live: true })">
                    <label class="label">Stories (published {{ $e['name'] }} stories only, in order)</label>
                    <template x-for="(r, i) in items" :key="r.id"><div class="mb-1.5 flex items-center gap-2 rounded bg-[var(--color-ink-850)] px-2 py-1.5 text-xs"><input type="hidden" name="article_ids[]" :value="r.id"><span class="num text-[var(--color-ink-400)]" x-text="i + 1"></span><span class="flex-1 truncate" x-text="r.title"></span><button type="button" @click="move(i, -1)">↑</button><button type="button" @click="move(i, 1)">↓</button><button type="button" @click="remove(i)">✕</button></div></template>
                    <div class="relative"><input x-model="q" @input.debounce.300ms="search()" class="input" placeholder="Search published stories…">
                        <div x-show="open && results.length" @click.outside="open = false" class="absolute z-20 mt-1 w-full rounded-lg border border-[var(--color-ink-600)] bg-[var(--color-ink-900)] p-1 shadow-2xl">
                            <template x-for="r in results"><button type="button" @click="add(r)" class="block w-full truncate rounded px-2 py-1.5 text-left text-xs hover:bg-[var(--color-ink-800)]" x-text="r.title"></button></template></div></div>
                </div>
                <button class="btn btn-primary w-full">Save draft</button>
            </form>
        @endforeach
    </section>

    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Confirmed sign-ups, last 30 days</h2></div>
            <div class="panel-body">@if ($growth->sum())<div class="h-44" x-data="chart(@js($growthChart))"><canvas x-ref="canvas"></canvas></div>@else<p class="py-8 text-center text-sm text-[var(--color-ink-400)]">No confirmed sign-ups in the last 30 days.</p>@endif</div>
        </section>
        <section class="panel overflow-x-auto">
            <div class="panel-head"><h2>Issues</h2></div>
            <table class="table">
                <thead><tr><th>Subject</th><th>Ed.</th><th>Stories</th><th>Status</th><th>Sent</th><th></th></tr></thead>
                <tbody>
                @forelse ($campaigns as $c)
                    <tr>
                        <td class="font-semibold" lang="{{ $c->locale }}">{{ $c->subject }}</td>
                        <td class="text-[0.625rem] font-extrabold uppercase">{{ $c->locale }}</td>
                        <td class="num">{{ count($c->article_ids ?? []) }}</td>
                        <td><span class="pill {{ $c->status === 'sent' ? 'pill-published' : ($c->status === 'sending' ? 'pill-editing' : 'pill-draft') }}">{{ ucfirst($c->status) }}</span></td>
                        <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ $c->sent_at ? local_time($c->sent_at)->format('d M H:i').' · '.number_format($c->recipients_count).' recipients' : '—' }}</td>
                        <td class="text-right">@if ($c->status === 'draft')
                            <form method="post" action="{{ route('admin.newsletter.send', $c) }}" data-confirm="Send “{{ $c->subject }}” to all {{ number_format($stats['by_locale'][$c->locale] ?? 0) }} active {{ editions()[$c->locale]['name'] ?? '' }} subscribers? This cannot be undone.">@csrf<button class="btn btn-primary btn-sm">Send</button></form>
                        @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-sm text-[var(--color-ink-400)]">No issues yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
        <section class="panel overflow-x-auto">
            <div class="panel-head"><h2>Subscribers</h2>
                <form class="flex gap-2"><input name="q" value="{{ request('q') }}" class="input !w-56 !py-1.5 text-xs" placeholder="Search email…"><button class="btn btn-secondary btn-sm">Search</button></form></div>
            <table class="table">
                <thead><tr><th>Email</th><th>Ed.</th><th>Status</th><th>Source</th><th>Joined</th><th></th></tr></thead>
                <tbody>
                @forelse ($subscribers as $s)
                    <tr>
                        <td class="num text-xs">{{ $s->email }}</td>
                        <td class="text-[0.625rem] font-extrabold uppercase">{{ $s->locale }}</td>
                        <td>@if ($s->unsubscribed_at)<span class="pill pill-archived">Unsubscribed</span>@elseif ($s->confirmed_at)<span class="pill pill-published">Active</span>@else<span class="pill pill-new">Pending</span>@endif</td>
                        <td class="text-xs">{{ $s->source ?: '—' }}</td>
                        <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time($s->created_at)->format('d M Y') }}</td>
                        <td class="text-right"><form method="post" action="{{ route('admin.newsletter.subscribers.destroy', $s) }}" data-confirm="Delete this subscriber record permanently?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-sm text-[var(--color-ink-400)]">No subscribers yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $subscribers->links('admin.partials.pagination') }}</div>
        </section>
    </div>
</div>
@endsection
