@extends('layouts.admin')
@section('title', 'Live Coverage')

@section('content')
<x-admin.page-header eyebrow="Live" title="Live Coverage Manager" subtitle="Open a live blog, assign journalists, post updates that appear instantly for readers, and close it when the event ends." />

<div class="grid gap-6 xl:grid-cols-[24rem_1fr]">
    <section class="panel">
        <div class="panel-head"><h2>Open new coverage</h2></div>
        <form method="post" action="{{ route('admin.live.store') }}" class="panel-body space-y-3">
            @csrf
            <div><label class="label">Edition</label><select name="locale" class="select">@foreach (editions() as $l => $e)<option value="{{ $l }}">{{ $e['name'] }}</option>@endforeach</select></div>
            <div><label class="label">Title</label><input name="title" required class="input" placeholder="Live: …"></div>
            <div><label class="label">Summary</label><textarea name="summary" rows="2" class="textarea"></textarea></div>
            <div><label class="label">Section</label><select name="category_id" class="select"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->nameFor('en') }}</option>@endforeach</select></div>
            <x-admin.media-input name="cover_media_id" label="Cover image" />
            <div><label class="label">Starts at (EAT)</label><input type="datetime-local" name="starts_at" class="input"></div>
            <div><label class="label">Journalists who may post</label><select name="journalists[]" multiple size="4" class="select">@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="go_live" value="1" class="check" checked> Go live immediately</label>
            <button class="btn btn-primary w-full">Create coverage</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>All coverage</h2></div>
        <table class="table">
            <thead><tr><th>Event</th><th>Ed.</th><th>Status</th><th>Updates</th><th>Started</th><th></th></tr></thead>
            <tbody>
            @foreach ($events as $ev)
                <tr>
                    <td class="font-semibold">{{ $ev->title }}<p class="text-xs font-normal text-[var(--color-ink-400)]">{{ $ev->journalists->pluck('name')->implode(', ') }}</p></td>
                    <td class="text-[0.625rem] font-extrabold uppercase">{{ $ev->locale }}</td>
                    <td><span class="pill {{ $ev->status === 'live' ? 'pill-live' : ($ev->status === 'scheduled' ? 'pill-scheduled' : 'pill-archived') }}">@if ($ev->status === 'live')<span class="live-dot"></span>@endif {{ ucfirst($ev->status) }}</span></td>
                    <td class="num">{{ $ev->all_updates_count }}</td>
                    <td class="num text-xs text-[var(--color-ink-400)]">{{ $ev->starts_at ? local_time($ev->starts_at)->format('d M H:i') : '—' }}</td>
                    <td class="text-right"><a href="{{ route('admin.live.desk', $ev) }}" class="btn {{ $ev->status === 'live' ? 'btn-primary' : 'btn-secondary' }} btn-sm">Open desk</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="p-4">{{ $events->links('admin.partials.pagination') }}</div>
    </section>
</div>
@endsection
