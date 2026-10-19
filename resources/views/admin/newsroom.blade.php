@extends('layouts.admin')
@section('title', editions()[$edition]['name'].' Newsroom')

@section('content')
@php $ed = editions()[$edition]; $so = $edition === 'so'; @endphp
<x-admin.page-header :eyebrow="$ed['name'].' Newsroom · '.url('/'.$edition)" :title="$so ? 'Qolka Wararka Soomaaliga' : 'English Newsroom'" :subtitle="$so ? 'Wararka, deg-degga, tebinta tooska ah iyo bogga hore ee daabacaadda Soomaaliga.' : 'Stories, breaking alerts, live coverage and the homepage of the English edition.'">
    <a href="{{ url('/'.$edition) }}" target="_blank" class="btn btn-secondary"><x-admin.icon name="external" /> {{ $so ? 'Eeg bogga' : 'View edition' }}</a>
    @can('articles.create')<a href="{{ route('admin.articles.create', $edition) }}" class="btn btn-primary"><x-admin.icon name="plus" /> {{ $so ? 'Sheeko cusub' : 'New story' }}</a>@endcan
</x-admin.page-header>

<div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
    @foreach ([['Published', $counts['published'] ?? 0, 'published'], ['Today', $publishedToday, null], ['In review', ($counts['submitted'] ?? 0) + ($counts['editing'] ?? 0), 'submitted'], ['Approved', $counts['approved'] ?? 0, 'approved'], ['Drafts', $counts['draft'] ?? 0, 'draft'], ['Scheduled', $counts['scheduled'] ?? 0, 'scheduled'], ['Readers · 7d', $readers7, null]] as [$label, $n, $status])
        <a @if ($status) href="{{ route('admin.articles.index', [$edition, 'status' => $status]) }}" @endif class="panel block p-4 {{ $status ? 'hover:border-[var(--color-ink-500)]' : '' }}">
            <p class="eyebrow">{{ $label }}</p><p class="num mt-2 text-3xl text-white">{{ number_format($n) }}</p>
        </a>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="panel xl:col-span-2">
        <div class="panel-head"><h2>Review queue</h2><a href="{{ route('admin.articles.index', [$edition, 'status' => 'submitted']) }}" class="text-xs font-bold text-[var(--color-ink-400)] hover:text-white">Open →</a></div>
        <table class="table"><tbody>
            @forelse ($queue as $t)
                <tr><td class="max-w-md" lang="{{ $edition }}"><a href="{{ story_edit_url($t->article_id, $edition) }}" class="font-semibold hover:text-[var(--color-signal)]">{{ $t->title }}</a>
                        <p class="text-[0.6875rem] text-[var(--color-ink-400)]">{{ $t->article->author?->name }} · waiting {{ $t->updated_at->diffForHumans(null, true) }} @if ($t->locked_by)· 🔒 {{ $t->locker?->name }}@endif</p></td>
                    <td><x-admin.status :status="$t->status" /></td></tr>
            @empty
                <tr><td class="text-[var(--color-ink-400)]">The queue is clear.</td></tr>
            @endforelse
        </tbody></table>
        <div class="grid gap-6 border-t border-[var(--color-ink-700)] p-4 md:grid-cols-2">
            <div><p class="eyebrow mb-2">Drafts</p>
                <ul class="space-y-1.5 text-sm">@forelse ($drafts as $t)<li class="truncate"><a href="{{ story_edit_url($t->article_id, $edition) }}" class="hover:text-[var(--color-signal)]">{{ $t->title }}</a></li>@empty<li class="text-[var(--color-ink-400)]">None</li>@endforelse</ul></div>
            <div><p class="eyebrow mb-2">Scheduled</p>
                <ul class="space-y-1.5 text-sm">@forelse ($scheduled as $t)<li class="flex gap-2"><span class="num text-xs text-[var(--color-ink-400)]">{{ local_time($t->scheduled_at)->format('d M H:i') }}</span><a href="{{ story_edit_url($t->article_id, $edition) }}" class="truncate hover:text-[var(--color-signal)]">{{ $t->title }}</a></li>@empty<li class="text-[var(--color-ink-400)]">None</li>@endforelse</ul></div>
        </div>
    </section>

    <section class="space-y-4">
        <div class="panel">
            <div class="panel-head"><h2>{{ $ed['name'] }} live desk</h2></div>
            <div class="panel-body space-y-2">
                @foreach ($breaking as $al)<p class="flex items-start gap-2 text-sm"><span class="live-dot mt-1.5 text-[var(--color-alarm)]"></span>{{ $al->headline }}</p>@endforeach
                @foreach ($live as $ev)<a href="{{ route('admin.live.desk', $ev) }}" class="flex items-center gap-2 text-sm font-semibold hover:text-[var(--color-signal)]"><span class="pill pill-live">LIVE</span>{{ $ev->title }}</a>@endforeach
                @if ($breaking->isEmpty() && $live->isEmpty())<p class="text-sm text-[var(--color-ink-400)]">No breaking alerts or live coverage running.</p>@endif
                <div class="grid grid-cols-2 gap-2 pt-2">
                    @can('breaking.manage')<a href="{{ route('admin.breaking.index', ['edition' => $edition]) }}" class="btn btn-danger btn-sm">Breaking</a>@endcan
                    @can('live.manage')<a href="{{ route('admin.live.index', ['edition' => $edition]) }}" class="btn btn-secondary btn-sm">Live coverage</a>@endcan
                    @can('homepage.manage')<a href="{{ route('admin.homepage.edit', $edition) }}" class="btn btn-secondary btn-sm">Homepage</a>@endcan
                    @can('video.manage')<a href="{{ route('admin.videos.index', ['locale' => $edition]) }}" class="btn btn-secondary btn-sm">Videos ({{ $videos }})</a>@endcan
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h2>Recently published</h2></div>
            <ul class="divide-y divide-[var(--color-ink-800)]">
                @forelse ($recent as $t)
                    <li class="px-4 py-2.5 text-sm"><a href="{{ $t->url() }}" target="_blank" class="line-clamp-2 hover:text-[var(--color-signal)]">{{ $t->title }}</a><p class="num text-[0.6875rem] text-[var(--color-ink-400)]">{{ local_time($t->published_at)->format('d M H:i') }} · {{ number_format($t->view_count) }} reads</p></li>
                @empty
                    <li class="px-4 py-4 text-sm text-[var(--color-ink-400)]">Nothing published yet.</li>
                @endforelse
            </ul>
        </div>
    </section>
</div>
@endsection
