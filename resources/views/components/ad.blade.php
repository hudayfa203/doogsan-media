@props(['slot'])
@php
    $ad = \App\Models\AdPlacement::serving($slot, edition())->with('media')->get()
        ->flatMap(fn ($a) => array_fill(0, max(1, $a->weight), $a))->shuffle()->first();
@endphp
@if ($ad && $ad->media)
    <aside {{ $attributes->class('ad-slot no-print') }} aria-label="{{ __('Advertisement') }}">
        <span class="ad-label">{{ __('Advertisement') }}</span>
        <a href="{{ $ad->target_url ? route('ad.click', $ad) : '#' }}" @if ($ad->target_url) target="_blank" rel="noopener sponsored" @endif
           x-data x-init="fetch('{{ route('api.ad.impression', $ad) }}', { method: 'POST', keepalive: true })">
            <img src="{{ $ad->media->url(1600) }}" alt="{{ $ad->alt_text ?: $ad->advertiser }}" loading="lazy" class="max-w-full h-auto">
        </a>
    </aside>
@endif
