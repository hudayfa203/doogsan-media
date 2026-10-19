@extends('layouts.admin')
@section('title', 'Advertising')

@section('content')
<x-admin.page-header eyebrow="Revenue" title="Advertising" subtitle="Direct-sold campaigns by slot. Impressions and clicks are counted first-party; empty slots are hidden on the site, never filled with placeholders.">
    <a href="{{ route('admin.ads.create') }}" class="btn btn-primary"><x-admin.icon name="plus" /> New campaign</a>
</x-admin.page-header>

<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
    @foreach ($inventory as $slot => $inv)
        <div class="panel p-4">
            <p class="text-xs font-bold text-[var(--color-ink-200)]">{{ $inv['label'] }}</p>
            <p class="mt-2 text-sm {{ $inv['active'] ? 'text-[var(--color-signal)]' : 'text-[var(--color-ink-400)]' }}"><span class="num text-xl font-bold">{{ $inv['active'] }}</span> serving</p>
        </div>
    @endforeach
</div>

<section class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th class="w-24"></th><th>Campaign</th><th>Slot</th><th>Edition</th><th>Flight</th><th class="text-right">Impr.</th><th class="text-right">Clicks</th><th class="text-right">CTR</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($ads as $ad)
            @php $serving = $ad->is_active && (! $ad->starts_at || $ad->starts_at->isPast()) && (! $ad->ends_at || $ad->ends_at->isFuture()); @endphp
            <tr>
                <td>@if ($ad->media)<img src="{{ $ad->media->url(480) }}" class="h-10 w-20 rounded object-cover" alt="">@endif</td>
                <td><a href="{{ route('admin.ads.edit', $ad) }}" class="font-semibold text-white hover:text-[var(--color-signal)]">{{ $ad->name }}</a><p class="text-xs text-[var(--color-ink-400)]">{{ $ad->advertiser }}</p></td>
                <td class="text-xs">{{ \App\Models\AdPlacement::SLOTS[$ad->slot] ?? $ad->slot }}</td>
                <td class="text-[0.625rem] font-extrabold uppercase">{{ $ad->locale ?: 'Both' }}</td>
                <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ $ad->starts_at ? local_time($ad->starts_at)->format('d M') : 'now' }} → {{ $ad->ends_at ? local_time($ad->ends_at)->format('d M Y') : 'open' }}</td>
                <td class="num text-right">{{ number_format($ad->impressions) }}</td>
                <td class="num text-right">{{ number_format($ad->clicks) }}</td>
                <td class="num text-right">{{ $ad->ctr() }}%</td>
                <td>@if ($serving)<span class="pill pill-published">Serving</span>@elseif (! $ad->is_active)<span class="pill pill-draft">Paused</span>@elseif ($ad->ends_at?->isPast())<span class="pill pill-archived">Ended</span>@else<span class="pill pill-scheduled">Scheduled</span>@endif</td>
                <td class="text-right whitespace-nowrap"><a href="{{ route('admin.ads.edit', $ad) }}" class="btn btn-secondary btn-sm">Edit</a>
                    <form method="post" action="{{ route('admin.ads.destroy', $ad) }}" class="inline" data-confirm="Delete this campaign?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form></td>
            </tr>
        @empty
            <tr><td colspan="10" class="p-6 text-sm text-[var(--color-ink-400)]">No campaigns. Ad slots stay hidden on the site until a campaign is active.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
