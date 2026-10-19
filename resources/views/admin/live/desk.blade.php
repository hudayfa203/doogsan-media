@extends('layouts.admin')
@section('title', 'Live desk')

@section('content')
<x-admin.page-header :eyebrow="'Live desk · '.strtoupper($event->locale)" :title="$event->title">
    <a href="{{ $event->url() }}" target="_blank" class="btn btn-secondary"><x-admin.icon name="external" /> View public page</a>
    @foreach (['live' => ['Go live', 'btn-primary'], 'closed' => ['Close coverage', 'btn-danger'], 'scheduled' => ['Set to scheduled', 'btn-secondary']] as $s => [$label, $cls])
        @if ($event->status !== $s)
            <form method="post" action="{{ route('admin.live.status', $event) }}" @if ($s === 'closed') data-confirm="Close this coverage? Readers will see it as ended." @endif>@csrf<input type="hidden" name="status" value="{{ $s }}"><button class="btn {{ $cls }}">{{ $label }}</button></form>
        @endif
    @endforeach
</x-admin.page-header>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="space-y-6">
        @if ($event->status !== 'closed')
            <section class="panel border-[var(--color-alarm)]/40" x-data="liveComposer({{ $event->id }})">
                <div class="panel-head"><h2 class="flex items-center gap-2">@if ($event->isLive())<span class="live-dot text-[var(--color-alarm)]"></span>@endif Post an update</h2><span class="text-xs text-[var(--color-ink-400)]">Publishes instantly</span></div>
                <div class="panel-body space-y-3">
                    <input x-model="headline" class="input !text-base font-semibold" placeholder="Headline (optional)">
                    <textarea x-model="body" rows="5" class="textarea !text-base" placeholder="What just happened? Plain text; blank lines make paragraphs. Bold: <strong>…</strong>"></textarea>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="addPhoto()" class="btn btn-secondary btn-sm"><x-admin.icon name="image" /> <span x-text="media ? 'Photo attached ✓' : 'Add photo'"></span></button>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="pinned" class="check"> Pin to top</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="key" class="check"> Key event</label>
                        <button type="button" @click="post()" :disabled="posting || !body.trim()" class="btn ml-auto bg-[var(--color-alarm)] text-white"><span x-text="posting ? 'Publishing…' : 'Publish update'"></span></button>
                    </div>
                </div>
            </section>
        @else
            <div class="rounded-lg border border-[var(--color-ink-600)] p-4 text-sm text-[var(--color-ink-300)]">This coverage is closed. Reopen it to post again.</div>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>Timeline</h2><span class="num text-xs text-[var(--color-ink-400)]">{{ $event->allUpdates->count() }} updates</span></div>
            <ol class="divide-y divide-[var(--color-ink-800)]">
                @foreach ($event->allUpdates as $u)
                    <li class="p-4" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="num text-[var(--color-alarm)]">{{ local_time($u->posted_at)->format('H:i') }}</span>
                            @if ($u->is_pinned)<span class="pill pill-approved">Pinned</span>@endif
                            @if ($u->is_key_event)<span class="pill pill-live">Key event</span>@endif
                            <span class="text-[var(--color-ink-400)]">{{ $u->author?->name }}</span>
                            <div class="ml-auto flex gap-1">
                                <button type="button" @click="edit = !edit" class="btn btn-ghost btn-sm">Edit</button>
                                <form method="post" action="{{ route('admin.live.pin', $u) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $u->is_pinned ? 'Unpin' : 'Pin' }}</button></form>
                                <form method="post" action="{{ route('admin.live.remove', $u) }}" data-confirm="Remove this update from the live page?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-[#ffb3ad]">Delete</button></form>
                            </div>
                        </div>
                        <div x-show="!edit">
                            @if ($u->headline)<p class="mt-2 font-bold">{{ $u->headline }}</p>@endif
                            <div class="mt-1 text-sm leading-relaxed text-[var(--color-ink-200)] [&_p+p]:mt-2">{!! $u->body !!}</div>
                            @if ($u->media)<img src="{{ $u->media->url(480) }}" class="mt-2 h-24 rounded" alt="">@endif
                        </div>
                        <form x-show="edit" x-cloak method="post" action="{{ route('admin.live.edit', $u) }}" class="mt-2 space-y-2">@csrf @method('PUT')
                            <input name="headline" value="{{ $u->headline }}" class="input"><textarea name="body" rows="4" class="textarea">{{ strip_tags(str_replace('</p><p>', "\n\n", $u->body)) }}</textarea>
                            <button class="btn btn-primary btn-sm">Save edit</button></form>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <aside class="space-y-4">
        <section class="panel">
            <div class="panel-head"><h2>Coverage details</h2></div>
            <form method="post" action="{{ route('admin.live.update', $event) }}" class="panel-body space-y-3">
                @csrf @method('PUT')
                <div><label class="label">Title</label><input name="title" value="{{ $event->title }}" class="input"></div>
                <div><label class="label">Summary</label><textarea name="summary" rows="3" class="textarea">{{ $event->summary }}</textarea></div>
                <div><label class="label">Section</label><select name="category_id" class="select"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($event->category_id == $c->id)>{{ $c->nameFor('en') }}</option>@endforeach</select></div>
                <x-admin.media-input name="cover_media_id" :value="$event->cover_media_id" label="Cover image" />
                <div><label class="label">Assigned journalists</label><select name="journalists[]" multiple size="5" class="select">@foreach ($users as $usr)<option value="{{ $usr->id }}" @selected($event->journalists->contains('id', $usr->id))>{{ $usr->name }}</option>@endforeach</select></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_on_homepage" value="1" @checked($event->show_on_homepage) class="check"> Feature on homepage while live</label>
                <button class="btn btn-secondary w-full">Save details</button>
            </form>
        </section>
    </aside>
</div>
@endsection
