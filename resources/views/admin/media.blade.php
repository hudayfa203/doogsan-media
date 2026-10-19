@extends('layouts.admin')
@section('title', 'Media Library')

@php
    $fmtBytes = function ($b) { $b = (int) $b; foreach (['B', 'KB', 'MB', 'GB'] as $u) { if ($b < 1024) return round($b, 1).' '.$u; $b /= 1024; } return round($b, 1).' TB'; };
    $type = request('type');
@endphp

@section('content')
<x-admin.page-header eyebrow="Content" title="Media Library" subtitle="Photos, video, audio and documents shared by both newsrooms. Images get responsive WebP sizes automatically. Always record the credit and alt text." />

<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach (['image' => 'Images', 'video' => 'Video', 'audio' => 'Audio', 'document' => 'Documents'] as $k => $v)
        <a href="{{ route('admin.media.index', ['type' => $k]) }}" class="panel panel-body block hover:border-[var(--color-signal-dim)] {{ $type === $k ? 'border-[var(--color-signal)]' : '' }}">
            <p class="text-xs font-bold uppercase tracking-wide text-[var(--color-ink-400)]">{{ $v }}</p>
            <p class="num mt-1 text-2xl font-extrabold text-white">{{ number_format($totals[$k]->c ?? 0) }}</p>
            <p class="num text-xs text-[var(--color-ink-500)]">{{ $fmtBytes($totals[$k]->bytes ?? 0) }}</p>
        </a>
    @endforeach
</div>

<div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
    <div>
        <form class="mb-4 flex flex-wrap gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Title, file name, credit or alt text…" class="input !w-72">
            <select name="type" class="select !w-auto"><option value="">All types</option>@foreach (['image', 'video', 'audio', 'document'] as $t)<option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>@endforeach</select>
            <select name="folder" class="select !w-auto"><option value="">All folders</option>@foreach ($folders as $f)<option value="{{ $f }}" @selected(request('folder') === $f)>{{ $f }}</option>@endforeach</select>
            <button class="btn btn-secondary">Filter</button>
            @if (request()->hasAny(['q', 'type', 'folder']))<a href="{{ route('admin.media.index') }}" class="btn btn-ghost">Clear</a>@endif
        </form>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6" x-data="{ open: null }">
            @forelse ($assets as $m)
                <div class="panel overflow-hidden">
                    <button type="button" @click="open = {{ $m->id }}" class="relative block aspect-[4/3] w-full bg-[var(--color-ink-850)]">
                        @if ($m->type === 'image')
                            <img src="{{ $m->url(480) }}" alt="{{ $m->alt_en }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                        @else
                            <span class="absolute inset-0 grid place-items-center text-xs font-extrabold uppercase tracking-widest text-[var(--color-ink-400)]"><x-admin.icon :name="$m->type === 'video' ? 'video' : ($m->type === 'audio' ? 'radio' : 'file')" />{{ $m->type }}</span>
                        @endif
                        @if (! $m->alt_en && $m->type === 'image')<span class="pill pill-editing absolute left-2 top-2">No alt</span>@endif
                    </button>
                    <div class="p-2">
                        <p class="truncate text-xs font-semibold text-white" title="{{ $m->title ?: $m->original_name }}">{{ $m->title ?: $m->original_name }}</p>
                        <p class="num truncate text-[0.6875rem] text-[var(--color-ink-500)]">{{ $m->width ? $m->width.'×'.$m->height.' · ' : '' }}{{ $fmtBytes($m->size_bytes) }}</p>
                    </div>

                    <div x-show="open === {{ $m->id }}" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4" @keydown.escape.window="open = null">
                        <div class="panel max-h-[90vh] w-full max-w-4xl overflow-y-auto" @click.outside="open = null">
                            <div class="panel-head"><h2 class="truncate">{{ $m->original_name }}</h2><button type="button" class="btn btn-ghost btn-sm" @click="open = null">✕</button></div>
                            <div class="grid gap-5 p-5 md:grid-cols-2">
                                <div>
                                    @if ($m->type === 'image')<img src="{{ $m->url(960) }}" alt="" class="w-full rounded-lg">
                                    @elseif ($m->type === 'video')<video src="{{ $m->url() }}" controls preload="metadata" class="w-full rounded-lg"></video>
                                    @elseif ($m->type === 'audio')<audio src="{{ $m->url() }}" controls class="w-full"></audio>
                                    @else<a href="{{ $m->url() }}" target="_blank" class="btn btn-secondary">Open document</a>@endif
                                    <dl class="mt-3 grid grid-cols-[7rem_1fr] gap-y-1 text-xs">
                                        <dt class="text-[var(--color-ink-500)]">Type</dt><dd>{{ $m->mime_type }}</dd>
                                        <dt class="text-[var(--color-ink-500)]">Size</dt><dd class="num">{{ $fmtBytes($m->size_bytes) }}{{ $m->width ? ' · '.$m->width.'×'.$m->height.' px' : '' }}</dd>
                                        <dt class="text-[var(--color-ink-500)]">Uploaded</dt><dd>{{ local_time($m->created_at)->format('d M Y H:i') }} by {{ $m->uploader?->name ?? '—' }}</dd>
                                        <dt class="text-[var(--color-ink-500)]">URL</dt><dd class="truncate"><a href="{{ $m->url() }}" target="_blank" class="text-[var(--color-signal)] hover:underline">{{ $m->url() }}</a></dd>
                                    </dl>
                                    <form method="post" action="{{ route('admin.media.destroy', $m) }}" class="mt-4" data-confirm="Delete this file permanently?">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete file</button></form>
                                </div>
                                <form method="post" action="{{ route('admin.media.update', $m) }}" class="space-y-3">
                                    @csrf @method('PUT')
                                    <div><label class="label">Title</label><input name="title" value="{{ $m->title }}" class="input"></div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div><label class="label">Alt text (English)</label><input name="alt_en" value="{{ $m->alt_en }}" class="input" maxlength="250"></div>
                                        <div><label class="label">Alt text (Somali)</label><input name="alt_so" value="{{ $m->alt_so }}" class="input" maxlength="250" lang="so"></div>
                                    </div>
                                    <div><label class="label">Caption (English)</label><textarea name="caption_en" rows="2" class="textarea">{{ $m->caption_en }}</textarea></div>
                                    <div><label class="label">Caption (Somali)</label><textarea name="caption_so" rows="2" class="textarea" lang="so">{{ $m->caption_so }}</textarea></div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div><label class="label">Credit</label><input name="credit" value="{{ $m->credit }}" class="input" placeholder="Photographer / Agency"></div>
                                        <div><label class="label">Copyright</label><input name="copyright" value="{{ $m->copyright }}" class="input"></div>
                                        <div><label class="label">License</label><input name="license" value="{{ $m->license }}" class="input"></div>
                                        <div><label class="label">Folder</label><input name="folder" value="{{ $m->folder }}" class="input" list="media-folders"></div>
                                    </div>
                                    <div><label class="label">Usage notes</label><input name="usage_notes" value="{{ $m->usage_notes }}" class="input" placeholder="e.g. Editorial use only, do not crop"></div>
                                    <button class="btn btn-primary">Save details</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="col-span-full panel panel-body text-sm text-[var(--color-ink-400)]">No files match. Upload the first one using the form.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $assets->links('admin.partials.pagination') }}</div>
    </div>

    <aside>
        <section class="panel sticky top-4">
            <div class="panel-head"><h2>Upload</h2></div>
            <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="panel-body space-y-3" x-data="{ n: 0 }">
                @csrf
                <label class="grid cursor-pointer place-items-center rounded-lg border border-dashed border-[var(--color-ink-600)] bg-[var(--color-ink-850)] px-4 py-8 text-center text-sm hover:border-[var(--color-signal)]">
                    <x-admin.icon name="image" />
                    <span class="mt-2 font-bold" x-text="n ? n + ' file(s) selected' : 'Choose files'"></span>
                    <span class="mt-1 text-xs text-[var(--color-ink-500)]">Up to 20 · max {{ round(config('doogsan.media.max_upload_kb') / 1024) }} MB each<br>{{ str_replace(',', ', ', config('doogsan.media.allowed_mimes')) }}</span>
                    <input type="file" name="files[]" multiple required class="sr-only" @change="n = $event.target.files.length" accept=".{{ str_replace(',', ',.', config('doogsan.media.allowed_mimes')) }}">
                </label>
                <div><label class="label">Credit for all</label><input name="credit" class="input" placeholder="Doogsan Media / Photographer"></div>
                <div><label class="label">Folder</label><input name="folder" class="input" list="media-folders" placeholder="e.g. garowe-2026"></div>
                <button class="btn btn-primary w-full">Upload</button>
            </form>
        </section>
    </aside>
</div>
<datalist id="media-folders">@foreach ($folders as $f)<option value="{{ $f }}">@endforeach</datalist>
@endsection
