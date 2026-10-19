@extends('layouts.admin')
@section('title', $video->exists ? 'Edit video' : 'Add video')

@section('content')
@php
    $durationValue = old('duration', $video->durationLabel());
    $pub = old('published_at', $video->published_at ? local_time($video->published_at)->format('Y-m-d\TH:i') : '');
@endphp
<x-admin.page-header eyebrow="Video & Podcasts" :title="$video->exists ? 'Edit: '.$video->title : 'Add video or podcast'">
    <a href="{{ route('admin.videos.index') }}" class="btn btn-ghost">← All videos</a>
    @if ($video->exists && $video->status === 'published')<a href="{{ $video->url() }}" target="_blank" class="btn btn-secondary">View on site ↗</a>@endif
</x-admin.page-header>

<form method="post" action="{{ $video->exists ? route('admin.videos.update', $video) : route('admin.videos.store') }}" class="grid gap-6 xl:grid-cols-[1fr_22rem]" x-data="{ provider: @js(old('provider', $video->provider)) }">
    @csrf @if ($video->exists) @method('PUT') @endif
    <section class="panel">
        <div class="panel-body space-y-4">
            @if ($video->is_sample)<p class="rounded-lg border border-[var(--color-amber)]/50 px-3 py-2 text-xs text-[var(--color-amber)]">Sample record — removed by <code>php artisan doogsan:sample --remove</code>.</p>@endif
            <div><label class="label">Title</label><input name="title" value="{{ old('title', $video->title) }}" required maxlength="200" class="input !text-base font-semibold"></div>
            <div><label class="label">URL slug</label><input name="slug" value="{{ old('slug', $video->slug) }}" maxlength="120" class="input" placeholder="generated from title"><p class="hint">Lowercase letters, numbers and hyphens.</p></div>
            <div><label class="label">Description</label><textarea name="description" rows="5" maxlength="3000" class="textarea">{{ old('description', $video->description) }}</textarea></div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="label">Provider</label>
                    <select name="provider" x-model="provider" class="select">
                        <option value="youtube">YouTube</option><option value="hls">HLS stream</option><option value="file">Uploaded video file</option><option value="audio">Audio (podcast)</option>
                    </select></div>
                <div x-show="provider === 'youtube'"><label class="label">YouTube ID or URL</label><input name="youtube_id" value="{{ old('youtube_id', $video->youtube_id) }}" class="input" placeholder="https://youtu.be/…"></div>
                <div x-show="provider !== 'youtube'"><label class="label">Source URL</label><input name="source_url" type="url" value="{{ old('source_url', $video->source_url) }}" class="input" placeholder="https://"><p class="hint">Or choose a file from the library below.</p></div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div x-show="provider === 'file' || provider === 'audio'"><x-admin.media-input name="media_id" :value="old('media_id', $video->media_id)" label="Media file" type="" /></div>
                <x-admin.media-input name="thumbnail_media_id" :value="old('thumbnail_media_id', $video->thumbnail_media_id)" label="Thumbnail" />
            </div>
        </div>
    </section>

    <aside class="space-y-6">
        <section class="panel"><div class="panel-body space-y-4">
            <div><label class="label">Edition</label><select name="locale" class="select">@foreach (editions() as $l => $e)<option value="{{ $l }}" @selected(old('locale', $video->locale) === $l)>{{ $e['name'] }}</option>@endforeach</select></div>
            <div><label class="label">Type</label><select name="kind" class="select">@foreach (['video' => 'Video', 'documentary' => 'Documentary', 'interview' => 'Interview', 'podcast' => 'Podcast'] as $k => $label)<option value="{{ $k }}" @selected(old('kind', $video->kind) === $k)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="label">Section</label><select name="category_id" class="select"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $video->category_id) == $c->id)>{{ $c->nameFor($video->locale ?: 'en') }}</option>@endforeach</select></div>
            <div><label class="label">Journalist</label><select name="author_id" class="select"><option value="">—</option>@foreach ($authors as $a)<option value="{{ $a->id }}" @selected(old('author_id', $video->author_id) == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            <div><label class="label">Duration</label><input name="duration" value="{{ $durationValue }}" class="input num" placeholder="mm:ss or h:mm:ss" maxlength="10"></div>
        </div></section>
        <section class="panel"><div class="panel-body space-y-4">
            <div><label class="label">Status</label><select name="status" class="select"><option value="draft" @selected(old('status', $video->status) === 'draft')>Draft</option><option value="published" @selected(old('status', $video->status) === 'published')>Published</option></select></div>
            <div><label class="label">Publish time (EAT)</label><input type="datetime-local" name="published_at" value="{{ $pub }}" class="input"><p class="hint">Leave blank to publish now. A future time schedules it.</p></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" class="check" @checked(old('is_featured', $video->is_featured))> Feature on the edition homepage</label>
            <button class="btn btn-primary w-full">Save</button>
        </div></section>
    </aside>
</form>
@endsection
