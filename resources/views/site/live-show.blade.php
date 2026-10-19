@extends('layouts.site')

@section('content')
<div x-data="liveblog({{ $event->id }}, @js($event->status))">
    <header class="bg-[var(--color-forest-950)] text-white">
        <div class="wrap grid gap-8 py-8 md:py-12 lg:grid-cols-12 lg:items-center">
            <div class="lg:col-span-7">
                <div class="flex items-center gap-3">
                    <span x-show="status === 'live'" class="badge badge-live"><span class="live-dot"></span>{{ __('Live') }}</span>
                    <span x-show="status === 'closed'" x-cloak class="badge bg-white/15 text-white">{{ __('Coverage ended') }}</span>
                    <span x-show="status === 'scheduled'" x-cloak class="badge bg-white/15 text-white">{{ __('Starting soon') }}</span>
                    @if ($event->category)<span class="kicker !text-[var(--color-lime-400)]">{{ $event->category->nameFor() }}</span>@endif
                </div>
                <h1 class="headline headline-xl mt-3 !text-white">{{ $event->title }}</h1>
                @if ($event->summary)<p class="mt-4 font-serif text-lg text-white/75">{{ $event->summary }}</p>@endif
                <p class="mt-4 text-xs font-bold text-white/50">{{ __('Started') }} {{ news_date($event->starts_at) }}@if ($event->ended_at) · {{ __('Ended') }} {{ news_date($event->ended_at) }}@endif</p>
            </div>
            @if ($event->cover)
                <div class="lg:col-span-5 media-frame ratio-16x9"><x-img :media="$event->cover" :width="960" :eager="true" /></div>
            @endif
        </div>
    </header>

    <div class="wrap grid gap-10 py-10 lg:grid-cols-[minmax(0,44rem)_1fr]">
        <div>
            <div x-show="pending.length" x-cloak class="sticky top-28 z-20 mb-4">
                <button @click="flush(); window.scrollTo({ top: $refs.list.offsetTop - 160, behavior: 'smooth' })" class="btn btn-primary w-full shadow-lg">
                    <span class="live-dot"></span> <span x-text="pending.length"></span> {{ __('new update(s) — show') }}
                </button>
            </div>

            <ol x-ref="list" class="timeline space-y-10" aria-live="polite">
                @forelse ($event->updates as $u)
                    <li id="u-{{ $u->id }}" data-update-id="{{ $u->id }}" @if ($u->is_pinned) data-pinned @endif class="timeline-item {{ $u->is_key_event ? 'is-key' : '' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-time :at="$u->posted_at" class="text-sm font-extrabold text-[var(--live)]" />
                            <span class="meta">{{ local_time($u->posted_at)->format('H:i') }} EAT</span>
                            @if ($u->is_pinned)<span class="badge badge-format">{{ __('Pinned') }}</span>@endif
                            @if ($u->is_key_event)<span class="badge badge-breaking">{{ __('Key event') }}</span>@endif
                        </div>
                        @if ($u->headline)<h2 class="headline headline-md mt-2">{{ $u->headline }}</h2>@endif
                        <div class="story-body !text-[1.0625rem] mt-2 [&>p:first-of-type]:first-letter:!text-[1em] [&>p:first-of-type]:first-letter:!float-none [&>p:first-of-type]:first-letter:!p-0">{!! $u->body !!}</div>
                        @if ($u->media)<img src="{{ $u->media->url(960) }}" alt="{{ $u->media->altFor() }}" class="mt-4 w-full" loading="lazy" data-zoom>@endif
                        @if ($u->author)<p class="meta mt-3">{{ $u->author->name }}</p>@endif
                    </li>
                @empty
                    <li class="text-[var(--muted)]">{{ __('Updates will appear here as soon as they are published.') }}</li>
                @endforelse
            </ol>

            <template x-ref="tpl">
                <li class="timeline-item">
                    <div class="flex flex-wrap items-center gap-2"><time data-f="time" data-rel class="text-sm font-extrabold text-[var(--live)]"></time></div>
                    <h2 data-f="headline" class="headline headline-md mt-2"></h2>
                    <div data-f="body" class="story-body !text-[1.0625rem] mt-2"></div>
                    <img data-f="image" class="mt-4 w-full" hidden alt="">
                    <p data-f="author" class="meta mt-3"></p>
                </li>
            </template>
        </div>

        <aside class="space-y-8">
            <div class="lg:sticky lg:top-36 space-y-8">
                <div class="rounded-sm border border-[var(--rule)] p-5" x-data="share(@js($event->url()), @js($event->title))">
                    <p class="text-sm font-extrabold">{{ __('Share this live coverage') }}</p>
                    <div class="mt-3 flex gap-2">
                        <a href="https://wa.me/?text={{ urlencode($event->title.' '.$event->url()) }}" target="_blank" rel="noopener" class="btn btn-ghost !px-3 !py-2"><x-social-icon network="whatsapp" class="h-4 w-4" /></a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($event->url()) }}" target="_blank" rel="noopener" class="btn btn-ghost !px-3 !py-2"><x-social-icon network="facebook" class="h-4 w-4" /></a>
                        <button @click="copy" class="btn btn-ghost !py-2 text-xs" x-text="copied ? '{{ __('Link copied') }}' : '{{ __('Copy link') }}'"></button>
                    </div>
                </div>
                <div x-data class="text-xs font-bold text-[var(--muted)]">
                    <p x-show="$store.live.mode === 'socket'"><span class="text-[var(--accent)]">●</span> {{ __('Updates arrive automatically') }}</p>
                    <p x-show="$store.live.mode !== 'socket'" x-cloak>{{ __('Checking for new updates every :s seconds', ['s' => config('doogsan.poll_interval')]) }}</p>
                </div>
                <x-ad slot="sidebar" />
            </div>
        </aside>
    </div>
</div>
@endsection
