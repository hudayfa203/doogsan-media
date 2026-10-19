@extends('layouts.admin')
@section('title', 'News tip #'.$tip->id)

@section('content')
<x-admin.page-header eyebrow="Confidential" :title="'News tip #'.$tip->id" subtitle="Your opening of this tip has been recorded in the audit log. Do not forward tip contents outside the newsroom.">
    <a href="{{ route('admin.inbox.index', ['tab' => 'tips']) }}" class="btn btn-ghost">← All tips</a>
</x-admin.page-header>

@if ($tip->wants_anonymity)
    <div class="mb-6 rounded-lg border border-[var(--color-alarm)]/50 bg-[color-mix(in_oklab,var(--color-alarm)_10%,transparent)] px-4 py-3 text-sm font-semibold text-[#ffb3ad]">The source asked to remain anonymous. Never publish or share their name or contact details.</div>
@endif

<div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
    <section class="panel">
        <div class="panel-head"><h2>What the source reported</h2><span class="num text-xs text-[var(--color-ink-400)]">{{ local_time($tip->created_at)->format('d M Y H:i') }} EAT</span></div>
        <div class="panel-body space-y-4">
            <div class="whitespace-pre-line text-sm leading-relaxed" lang="{{ $tip->locale }}">{{ $tip->details }}</div>
            @if ($tip->media)
                <div class="border-t border-[var(--color-ink-700)] pt-4">
                    <p class="eyebrow mb-2">Attachment</p>
                    @if (str_starts_with((string) $tip->media->mime_type, 'image/'))
                        <a href="{{ $tip->media->url() }}" target="_blank"><img src="{{ $tip->media->url(960) }}" alt="" class="max-h-96 rounded-lg"></a>
                    @else
                        <a href="{{ $tip->media->url() }}" target="_blank" class="btn btn-secondary btn-sm">Open attachment ↗</a>
                    @endif
                </div>
            @endif
        </div>
    </section>
    <aside class="space-y-4">
        <section class="panel"><div class="panel-body space-y-3 text-sm">
            <div><p class="eyebrow">Source name</p><p>{{ $tip->name ?: 'Not given' }}</p></div>
            <div><p class="eyebrow">Contact</p><p class="break-all">{{ $tip->contact ?: 'Not given' }}</p></div>
            <div><p class="eyebrow">Location</p><p>{{ $tip->location ?: '—' }}</p></div>
            <div><p class="eyebrow">Edition</p><p>{{ editions()[$tip->locale]['name'] ?? $tip->locale }}</p></div>
        </div></section>
        <form method="post" action="{{ route('admin.inbox.tip.update', $tip) }}" class="panel panel-body space-y-3">
            @csrf @method('PATCH')
            <div><label class="label">Status</label><select name="status" class="select">@foreach (['new', 'reviewing', 'assigned', 'closed'] as $s)<option value="{{ $s }}" @selected($tip->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
            <div><label class="label">Assign to</label><select name="assigned_to" class="select"><option value="">— Unassigned —</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected($tip->assigned_to == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <button class="btn btn-primary w-full">Update tip</button>
        </form>
    </aside>
</div>
@endsection
