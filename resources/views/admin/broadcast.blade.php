@extends('layouts.admin')
@section('title', 'Live TV Studio')

@section('content')
<x-admin.page-header eyebrow="Broadcast" title="Live TV Studio" subtitle="Readers see ON AIR only when a channel is switched on here and has a working stream source. Otherwise the Live TV page shows an honest off-air screen with the schedule.">
    <a href="{{ lroute('tv', [], 'en') }}" target="_blank" class="btn btn-secondary btn-sm">View /en Live TV ↗</a>
    <a href="{{ lroute('tv', [], 'so') }}" target="_blank" class="btn btn-secondary btn-sm">View /so TV Toos ↗</a>
</x-admin.page-header>

<div class="space-y-6">
    @forelse ($channels as $c)
        @php $onAir = $c->isOnAir(); $now = $c->currentProgram(); @endphp
        <section class="panel {{ $onAir ? 'border-[var(--color-alarm)]/60' : '' }}" x-data="{ edit: false }">
            <div class="panel-head">
                <h2 class="flex items-center gap-3">
                    {{ $c->name }}
                    @if ($onAir)
                        <span class="pill pill-live"><span class="live-dot"></span> ON AIR</span>
                    @elseif ($c->is_live && ! $c->hasSource())
                        <span class="pill pill-editing">Marked live — no source</span>
                    @else
                        <span class="pill pill-archived">Off air</span>
                    @endif
                    @if (! $c->is_enabled)<span class="pill pill-draft">Disabled</span>@endif
                </h2>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="{{ route('admin.broadcast.channels.check', $c) }}">@csrf<button class="btn btn-ghost btn-sm">Check stream</button></form>
                    <button type="button" @click="edit = !edit" class="btn btn-secondary btn-sm">Settings</button>
                    <form method="post" action="{{ route('admin.broadcast.channels.live', $c) }}" data-confirm="{{ $c->is_live ? 'Take '.$c->name.' off air?' : 'Put '.$c->name.' ON AIR for readers now?' }}">@csrf
                        @if ($c->is_live)
                            <button class="btn btn-danger btn-sm">■ Go off air</button>
                        @else
                            <button class="btn btn-primary btn-sm" @disabled(! $c->hasSource())>● Go on air</button>
                        @endif
                    </form>
                </div>
            </div>
            <div class="panel-body grid gap-4 text-sm md:grid-cols-4">
                <div><p class="eyebrow">Source</p><p class="mt-1 font-semibold">{{ strtoupper($c->source_type) }} · {{ $c->hasSource() ? 'configured' : 'not configured' }}</p></div>
                <div><p class="eyebrow">Stream health</p>
                    <p class="mt-1 font-semibold {{ $c->health_status === 'healthy' ? 'text-[var(--color-signal)]' : ($c->health_status === 'down' ? 'text-[var(--color-alarm)]' : 'text-[var(--color-ink-300)]') }}">{{ ucfirst($c->health_status ?? 'unknown') }}</p>
                    <p class="text-xs text-[var(--color-ink-400)]">{{ $c->health_message }} @if ($c->health_checked_at)· {{ $c->health_checked_at->diffForHumans() }}@endif</p></div>
                <div><p class="eyebrow">On air since</p><p class="num mt-1">{{ $onAir && $c->went_live_at ? local_time($c->went_live_at)->format('d M H:i').' EAT' : '—' }}</p></div>
                <div><p class="eyebrow">Scheduled now</p><p class="mt-1">{{ $now ? $now->title_en.' / '.$now->title_so : 'Nothing scheduled' }}</p></div>
                @if (! $c->hasSource())
                    <p class="text-xs text-[var(--color-amber)] md:col-span-4">Add an HLS manifest URL, a YouTube live video ID or an external player URL in Settings before going on air.</p>
                @endif
            </div>
            <form x-show="edit" x-cloak method="post" action="{{ route('admin.broadcast.channels.update', $c) }}" class="panel-body grid gap-4 border-t border-[var(--color-ink-700)] md:grid-cols-2">
                @csrf @method('PUT')
                @include('admin.partials.broadcast-channel-fields', ['channel' => $c])
                <div class="flex items-center justify-between gap-2 md:col-span-2">
                    <button class="btn btn-primary">Save channel</button>
                    <button form="del-channel-{{ $c->id }}" class="btn btn-ghost btn-sm text-[var(--color-alarm)]">Remove channel</button>
                </div>
            </form>
            <form id="del-channel-{{ $c->id }}" method="post" action="{{ route('admin.broadcast.channels.destroy', $c) }}" data-confirm="Remove {{ $c->name }} and its schedule?">@csrf @method('DELETE')</form>
        </section>
    @empty
        <div class="panel panel-body text-sm text-[var(--color-ink-400)]">No channels yet. Add one below. Until then the public Live TV page shows the off-air state.</div>
    @endforelse

    <div class="grid gap-6 xl:grid-cols-[26rem_1fr]">
        <div class="space-y-6">
            <section class="panel">
                <div class="panel-head"><h2>Schedule a programme</h2></div>
                <form method="post" action="{{ route('admin.broadcast.programs.store') }}" class="panel-body space-y-3">
                    @csrf
                    <div><label class="label">Channel</label><select name="broadcast_channel_id" class="select" required>@foreach ($channels as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Title (English)</label><input name="title_en" required class="input" maxlength="160"></div>
                        <div><label class="label">Title (Somali)</label><input name="title_so" required class="input" maxlength="160" lang="so"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Description (EN)</label><textarea name="description_en" rows="2" class="textarea"></textarea></div>
                        <div><label class="label">Description (SO)</label><textarea name="description_so" rows="2" class="textarea" lang="so"></textarea></div>
                    </div>
                    <div><label class="label">Presenter</label><input name="presenter" class="input"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Starts (EAT)</label><input type="datetime-local" name="starts_at" required class="input"></div>
                        <div><label class="label">Ends (EAT)</label><input type="datetime-local" name="ends_at" required class="input"></div>
                    </div>
                    <div><label class="label">Repeat daily for</label><select name="repeat_days" class="select"><option value="0">Once only</option><option value="6">7 days</option><option value="13">14 days</option><option value="29">30 days</option></select></div>
                    <button class="btn btn-primary w-full" @disabled($channels->isEmpty())>Add to schedule</button>
                </form>
            </section>
            <section class="panel" x-data="{ open: {{ $channels->isEmpty() ? 'true' : 'false' }} }">
                <div class="panel-head"><h2>Add a channel</h2><button type="button" @click="open = !open" class="btn btn-ghost btn-sm" x-text="open ? 'Hide' : 'Show'"></button></div>
                <form x-show="open" method="post" action="{{ route('admin.broadcast.channels.store') }}" class="panel-body grid gap-4">
                    @csrf
                    @include('admin.partials.broadcast-channel-fields', ['channel' => new \App\Models\BroadcastChannel(['source_type' => 'hls', 'is_enabled' => true, 'show_live_indicator' => true])])
                    <button class="btn btn-primary">Add channel</button>
                </form>
            </section>
        </div>

        <section class="panel overflow-x-auto">
            <div class="panel-head"><h2>Programme schedule</h2><span class="text-xs text-[var(--color-ink-400)]">Times in EAT (Africa/Mogadishu)</span></div>
            <table class="table">
                <thead><tr><th>When</th><th>Programme</th><th>Channel</th><th>Presenter</th><th></th></tr></thead>
                <tbody>
                @forelse ($programs as $p)
                    @php $running = $p->starts_at->isPast() && $p->ends_at->isFuture(); @endphp
                    <tr class="{{ $p->ends_at->isPast() ? 'opacity-50' : '' }}">
                        <td class="num whitespace-nowrap text-xs">{{ local_time($p->starts_at)->format('D d M H:i') }}–{{ local_time($p->ends_at)->format('H:i') }}
                            @if ($running)<span class="pill pill-scheduled ml-1">Scheduled now</span>@endif</td>
                        <td><span class="font-semibold">{{ $p->title_en }}</span><p class="text-xs text-[var(--color-ink-400)]" lang="so">{{ $p->title_so }}</p></td>
                        <td class="text-xs">{{ $p->channel?->name }}</td>
                        <td class="text-xs">{{ $p->presenter ?: '—' }}</td>
                        <td class="text-right"><form method="post" action="{{ route('admin.broadcast.programs.destroy', $p) }}" data-confirm="Remove this programme?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Remove</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-sm text-[var(--color-ink-400)]">No programmes scheduled.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>
@endsection
