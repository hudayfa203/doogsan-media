@extends('layouts.admin')
@section('title', editions()[$edition]['name'].' Newsroom — Articles')

@section('content')
@php $ed = editions()[$edition]; $st = request('status'); $f = request('filter'); @endphp
<x-admin.page-header :eyebrow="$ed['name'].' Newsroom'" :title="$edition === 'so' ? 'Wararka Soomaaliga' : 'English Stories'" :subtitle="'Stories published only at '.url('/'.$edition).'. The other edition has its own newsroom.'">
    @can('articles.create')<a href="{{ route('admin.articles.create', $edition) }}" class="btn btn-primary"><x-admin.icon name="plus" /> {{ $edition === 'so' ? 'Sheeko cusub' : 'New story' }}</a>@endcan
</x-admin.page-header>

<div class="mb-4 flex flex-wrap gap-1 border-b border-[var(--color-ink-700)]">
    <a href="{{ route('admin.articles.index', $edition) }}" class="tab {{ ! $st && ! $f ? 'is-active' : '' }}">All</a>
    @foreach (\App\Models\ArticleTranslation::STATUSES as $s)
        <a href="{{ route('admin.articles.index', [$edition, 'status' => $s]) }}" class="tab {{ $st === $s ? 'is-active' : '' }}">{{ __('status.'.$s) }} <span class="num ml-1 text-[var(--color-ink-500)]">{{ $counts[$s] ?? 0 }}</span></a>
    @endforeach
    <a href="{{ route('admin.articles.index', [$edition, 'filter' => 'mine']) }}" class="tab {{ $f === 'mine' ? 'is-active' : '' }}">Mine</a>
    <a href="{{ route('admin.articles.index', [$edition, 'filter' => 'overdue']) }}" class="tab {{ $f === 'overdue' ? 'is-active' : '' }}">Overdue</a>
    @can('articles.delete')<a href="{{ route('admin.articles.index', [$edition, 'filter' => 'trash']) }}" class="tab {{ $f === 'trash' ? 'is-active' : '' }}">Trash</a>@endcan
</div>

<form class="mb-4 flex flex-wrap gap-2">
    @foreach (request()->only('status', 'filter') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <input name="q" value="{{ request('q') }}" placeholder="Headline contains…" class="input !w-64">
    <select name="category" class="select !w-auto"><option value="">All sections</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->nameFor($edition) }}</option>@endforeach</select>
    <select name="format" class="select !w-auto"><option value="">All formats</option>@foreach (config('doogsan.formats') as $k => $fm)<option value="{{ $k }}" @selected(request('format') === $k)>{{ $fm[$edition] }}</option>@endforeach</select>
    <select name="author" class="select !w-auto"><option value="">All journalists</option>@foreach ($authors as $a)<option value="{{ $a->id }}" @selected(request('author') == $a->id)>{{ $a->name }}</option>@endforeach</select>
    <button class="btn btn-secondary">Filter</button>
</form>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th class="w-16"></th><th>Story</th><th>Status</th><th>Section</th><th>Byline</th><th>Priority</th><th>Published</th><th></th></tr></thead>
        <tbody>
        @forelse ($stories as $t)
            @php $a = $t->article; @endphp
            <tr>
                <td>@if ($a->featuredMedia)<img src="{{ $a->featuredMedia->url(480) }}" class="h-10 w-14 rounded object-cover" alt="">@else<div class="h-10 w-14 rounded bg-[var(--color-ink-800)]"></div>@endif</td>
                <td class="max-w-md" lang="{{ $edition }}">
                    <a href="{{ story_edit_url($a, $edition) }}" class="font-semibold text-white hover:text-[var(--color-signal)]">{{ $t->title }}</a>
                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[0.6875rem] text-[var(--color-ink-400)]">
                        <span>{{ config("doogsan.formats.{$a->format}.{$edition}") }}</span>
                        @if ($a->is_breaking)<span class="pill pill-live !py-0">Breaking</span>@endif
                        @if ($a->translations->count() > 1)<span title="Also has a version in the other edition">⇄ {{ strtoupper($edition === 'en' ? 'so' : 'en') }}</span>@endif
                        @if ($a->deadline_at && ! in_array($t->status, ['published', 'archived']))<span class="{{ $a->deadline_at->isPast() ? 'text-[var(--color-alarm)]' : '' }}">Due {{ local_time($a->deadline_at)->format('d M H:i') }}</span>@endif
                        @if ($t->locked_by && $t->locked_at?->gt(now()->subMinutes(10)))<span class="text-[#ffd98a]">🔒 {{ $t->locker?->name }}</span>@endif
                    </div>
                </td>
                <td><x-admin.status :status="$t->status" /></td>
                <td class="text-[var(--color-ink-300)]">{{ $a->category?->nameFor($edition) ?? '—' }}</td>
                <td class="text-[var(--color-ink-300)]">{{ $a->author?->name ?? '—' }}</td>
                <td><span class="num {{ $a->priority <= 2 ? 'text-[var(--color-signal)]' : 'text-[var(--color-ink-400)]' }}">P{{ $a->priority }}</span></td>
                <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ $t->published_at ? local_time($t->published_at)->format('d M H:i') : '—' }}</td>
                <td class="whitespace-nowrap text-right">
                    <a href="{{ story_edit_url($a, $edition) }}" class="btn btn-secondary btn-sm">Edit</a>
                    @if ($t->isLive())<a href="{{ $t->url() }}" target="_blank" class="btn btn-ghost btn-sm" title="View on site"><x-admin.icon name="external" /></a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="py-14 text-center">
                <p class="font-bold text-white">{{ $edition === 'so' ? 'Weli sheeko lama qorin.' : 'No stories yet.' }}</p>
                <p class="mt-1 text-sm text-[var(--color-ink-400)]">Stories you create here appear only in the {{ $ed['name'] }} edition.</p>
                @can('articles.create')<a href="{{ route('admin.articles.create', $edition) }}" class="btn btn-primary mt-4">Write the first {{ $ed['name'] }} story</a>@endcan
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4 text-sm">{{ $stories->links('admin.partials.pagination') }}</div>
@endsection
