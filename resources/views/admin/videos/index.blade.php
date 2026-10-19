@extends('layouts.admin')
@section('title', 'Video & Podcasts')

@section('content')
@php $kinds = ['video' => 'Video', 'documentary' => 'Documentary', 'interview' => 'Interview', 'podcast' => 'Podcast']; @endphp
<x-admin.page-header eyebrow="Broadcast" title="Video & Podcasts" subtitle="Each edition has its own video library. A video added to the Somali edition appears only at /so.">
    <a href="{{ route('admin.videos.create') }}" class="btn btn-primary"><x-admin.icon name="plus" /> Add video</a>
</x-admin.page-header>

<div class="mb-4 flex flex-wrap gap-1 border-b border-[var(--color-ink-700)]">
    <a href="{{ route('admin.videos.index', request()->only('kind')) }}" class="tab {{ ! request('locale') ? 'is-active' : '' }}">Both editions</a>
    @foreach (editions() as $l => $e)
        <a href="{{ route('admin.videos.index', array_merge(request()->only('kind'), ['locale' => $l])) }}" class="tab {{ request('locale') === $l ? 'is-active' : '' }}">{{ $e['name'] }}</a>
    @endforeach
</div>
<form class="mb-4 flex flex-wrap gap-2">
    @if (request('locale'))<input type="hidden" name="locale" value="{{ request('locale') }}">@endif
    <select name="kind" class="select !w-auto" onchange="this.form.submit()"><option value="">All types</option>@foreach ($kinds as $k => $label)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $label }}</option>@endforeach</select>
</form>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
    @forelse ($videos as $v)
        <article class="panel overflow-hidden">
            <a href="{{ route('admin.videos.edit', $v) }}" class="relative block aspect-video bg-[var(--color-ink-850)]">
                @if ($v->thumbnailUrl(480))<img src="{{ $v->thumbnailUrl(480) }}" alt="" class="h-full w-full object-cover" loading="lazy">@endif
                @if ($v->durationLabel())<span class="num absolute bottom-2 right-2 rounded bg-black/75 px-1.5 py-0.5 text-[0.6875rem] text-white">{{ $v->durationLabel() }}</span>@endif
                @if ($v->is_sample)<span class="absolute left-2 top-2 rounded bg-[var(--color-amber)] px-1.5 py-0.5 text-[0.625rem] font-extrabold uppercase text-black">Sample</span>@endif
            </a>
            <div class="p-3">
                <div class="mb-1 flex flex-wrap items-center gap-1.5 text-[0.6875rem] text-[var(--color-ink-400)]">
                    <span class="font-extrabold uppercase">{{ $v->locale }}</span> · <span>{{ $kinds[$v->kind] ?? $v->kind }}</span>
                    <x-admin.status :status="$v->status" />
                    @if ($v->is_featured)<span class="pill pill-approved">Featured</span>@endif
                </div>
                <a href="{{ route('admin.videos.edit', $v) }}" class="line-clamp-2 font-semibold text-white hover:text-[var(--color-signal)]" lang="{{ $v->locale }}">{{ $v->title }}</a>
                <div class="mt-2 flex items-center justify-between text-xs text-[var(--color-ink-400)]">
                    <span class="num">{{ $v->published_at ? local_time($v->published_at)->format('d M Y') : 'Not published' }}</span>
                    <div class="flex gap-1">
                        @if ($v->status === 'published')<a href="{{ $v->url() }}" target="_blank" class="btn btn-ghost btn-sm">View ↗</a>@endif
                        <form method="post" action="{{ route('admin.videos.destroy', $v) }}" data-confirm="Delete this video?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form>
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="panel panel-body text-sm text-[var(--color-ink-400)] sm:col-span-2 lg:col-span-3">No videos yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $videos->links('admin.partials.pagination') }}</div>
@endsection
