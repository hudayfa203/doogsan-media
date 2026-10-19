@extends('layouts.site')

@section('content')
@php
    $onAir = $channel?->isOnAir();
    $locale = edition();
@endphp
<section class="bg-[var(--color-forest-950)] text-white">
    <div class="wrap py-8 md:py-10">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div>
                <p class="kicker !text-[var(--color-lime-400)]">{{ $channel?->name ?? 'Doogsan TV' }}</p>
                <h1 class="headline headline-lg !text-white mt-1">{{ __('Live TV') }}</h1>
            </div>
            <span x-data class="badge" :class="$store.live.tvOnAir ? 'badge-live' : 'bg-white/15 text-white'">
                <span class="live-dot" x-show="$store.live.tvOnAir"></span>
                <span x-text="$store.live.tvOnAir ? '{{ __('On air') }}' : '{{ __('Off air') }}'">{{ $onAir ? __('On air') : __('Off air') }}</span>
            </span>
        </div>

        <div class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <div class="relative ratio-16x9 overflow-hidden bg-black">
                    @if ($onAir && $channel->source_type === 'youtube')
                        <iframe src="{{ $channel->playbackUrl() }}" class="absolute inset-0 h-full w-full" title="{{ $channel->name }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                    @elseif ($onAir && in_array($channel->source_type, ['hls', 'external']))
                        <div x-data="tvPlayer(@js($channel->playbackUrl()), @js($channel->source_type === 'hls' ? 'hls' : 'file'))" class="absolute inset-0">
                            <video x-ref="video" class="h-full w-full" controls playsinline autoplay muted @if ($channel->source_type !== 'hls') src="{{ $channel->playbackUrl() }}" @endif poster="{{ $channel->poster?->url(1600) }}"></video>
                            <div x-show="error" x-cloak class="absolute inset-0 grid place-items-center bg-black/80 p-6 text-center">
                                <div><p class="font-bold">{{ __('The stream could not be loaded.') }}</p><p class="mt-1 text-sm text-white/60">{{ __('Please try again in a moment.') }}</p></div>
                            </div>
                        </div>
                    @else
                        @if ($channel?->poster)<img src="{{ $channel->poster->url(1600) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">@endif
                        <div class="absolute inset-0 grid place-items-center p-6 text-center">
                            <div>
                                <span class="mx-auto grid h-16 w-16 place-items-center rounded-full border-2 border-white/30"><svg class="h-7 w-7 text-white/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="m17 2-5 5-5-5"/></svg></span>
                                <p class="mt-4 font-serif text-2xl font-semibold">{{ __('We are not broadcasting right now') }}</p>
                                @php $next = $schedule->first(fn ($p) => $p->starts_at->isFuture()); @endphp
                                @if ($next)
                                    <p class="mt-2 text-white/70">{{ __('Next:') }} <strong class="text-white">{{ $next->titleFor($locale) }}</strong> · {{ local_time($next->starts_at)->format('H:i') }} EAT</p>
                                @endif
                                <p class="mt-1 text-sm text-white/50">{{ __('This page updates automatically when we go live.') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-white/50">{{ $current ? __('Now') : __('Schedule') }}</p>
                        <p class="font-serif text-xl font-semibold">{{ $current?->titleFor($locale) ?? __('Doogsan TV') }}</p>
                        @if ($current?->descriptionFor($locale))<p class="text-sm text-white/65">{{ $current->descriptionFor($locale) }}</p>@endif
                    </div>
                    <div x-data="share(@js(lroute('tv')), 'Doogsan Media Live TV')">
                        <button @click="copy" class="btn border border-white/20 !py-2 text-sm text-white" x-text="copied ? '{{ __('Link copied') }}' : '{{ __('Share') }}'">{{ __('Share') }}</button>
                    </div>
                </div>
            </div>

            <aside class="lg:col-span-4">
                <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-[var(--color-lime-400)] mb-3">{{ __('Programme schedule') }} · EAT</p>
                <ol class="divide-y divide-white/10 border-y border-white/10">
                    @forelse ($schedule as $p)
                        <li class="flex gap-4 py-3 {{ $p->isOnNow() ? 'text-white' : 'text-white/70' }}">
                            <span class="w-24 flex-none tabular-nums text-sm font-bold">{{ local_time($p->starts_at)->format('H:i') }}–{{ local_time($p->ends_at)->format('H:i') }}</span>
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $p->titleFor($locale) }}</p>
                                <p class="text-xs text-white/50">{{ news_date($p->starts_at, false) }}@if ($p->presenter) · {{ $p->presenter }}@endif</p>
                            </div>
                            @if ($p->isOnNow() && $onAir)<span class="ml-auto badge badge-live self-start">{{ __('Now') }}</span>@endif
                        </li>
                    @empty
                        <li class="py-3 text-white/60">{{ __('No programmes scheduled.') }}</li>
                    @endforelse
                </ol>
            </aside>
        </div>
    </div>
</section>

@if ($recent->isNotEmpty())
    <section class="wrap mt-12">
        <x-section-head :title="__('Recent broadcasts & video')" :href="lroute('videos')" />
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($recent as $v)
                @include('site.partials.video-card', ['v' => $v])
            @endforeach
        </div>
    </section>
@endif
@endsection
