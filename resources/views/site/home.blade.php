@extends('layouts.site')

@section('content')
@php
    $locale = edition();
    // Pack consecutive "list"-style section modules into 3-up newspaper columns.
    $blocks = [];
    foreach ($sections as $s) {
        $isList = in_array($s['type'], ['category', 'format'], true) && ($s['style'] ?? '') === 'list';
        if ($isList && ($last = end($blocks)) && $last['type'] === 'columns' && count($last['items']) < 3) {
            $blocks[array_key_last($blocks)]['items'][] = $s;
        } elseif ($isList) {
            $blocks[] = ['type' => 'columns', 'items' => [$s]];
        } else {
            $blocks[] = $s;
        }
    }
@endphp

<h1 class="sr-only">Doogsan Media — {{ __('Latest news') }}</h1>

@if (! $lead)
    <section class="wrap py-24 text-center">
        <img src="{{ asset('images/brand/doogsan-mark-light.png') }}" alt="" class="mx-auto h-16 opacity-60 dark:hidden">
        <h2 class="headline headline-lg mt-6">{{ __('The newsroom is getting ready.') }}</h2>
        <p class="dek mt-3">{{ __('No stories have been published in this edition yet.') }}</p>
    </section>
@else

{{-- ── Top of the front page ─────────────────────────────────────── --}}
<section class="wrap pt-6 lg:pt-8" aria-label="{{ __('Top stories') }}">
    <div class="grid grid-cols-1 gap-x-8 gap-y-8 lg:grid-cols-12">
        {{-- Lead --}}
        <div class="lg:col-span-6 lg:border-r lg:border-[var(--rule)] lg:pr-8">
            <x-story :t="$lead" variant="lead" :eager="true" />
            @if ($lead->article->liveEvent?->status === 'live')
                @php $ev = $lead->article->liveEvent->load(['updates' => fn ($q) => $q->limit(3)]); @endphp
                <ul class="mt-5 space-y-3 border-l-2 border-[var(--live)] pl-4">
                    @foreach ($ev->updates as $u)
                        <li class="text-sm"><x-time :at="$u->posted_at" class="font-bold text-[var(--live)] mr-1.5" /> {{ $u->headline ?: \Illuminate\Support\Str::limit(strip_tags($u->body), 120) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Compact "Latest" for phones & tablets: two headlines, minimal height --}}
        @php
            $compact = $rail->take(4)->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'url' => $r->url(), 'published_at' => $r->published_at?->toIso8601String()])->values();
        @endphp
        <section x-data="latestCompact(@js($compact))" class="min-w-0 lg:hidden -mt-2 border-y border-[var(--rule)] py-2.5" aria-label="{{ __('Latest') }}">
            <div class="flex items-center justify-between gap-3">
                <h2 class="flex items-center gap-1.5 text-[0.6875rem] font-extrabold uppercase tracking-[0.12em] text-[var(--fg)]">
                    <span x-show="$store.live.liveNow" x-cloak class="flex items-center gap-1 text-[var(--live)]"><span class="live-dot !h-1.5 !w-1.5"></span>{{ __('Live') }} ·</span>
                    {{ __('Latest') }}
                </h2>
                <a href="{{ lroute('latest') }}" class="text-[0.6875rem] font-bold text-[var(--accent)]">{{ __('View all') }} →</a>
            </div>
            <ul class="mt-1.5 divide-y divide-[var(--rule)]">
                <template x-for="s in items" :key="s.id">
                    <li class="flex items-baseline gap-2.5 py-1.5" :class="s.fresh && 'is-new'">
                        <time :datetime="s.published_at" class="w-12 flex-none text-[0.6875rem] font-bold tabular-nums text-[var(--muted)]" x-text="short(s.published_at)"></time>
                        <a :href="s.url" class="min-w-0 flex-1 truncate font-serif text-[0.9375rem] font-semibold leading-snug" x-text="s.title"></a>
                    </li>
                </template>
                {{-- No-JS fallback --}}
                <noscript>@foreach ($rail->take(2) as $r)<li class="py-1.5"><a href="{{ $r->url() }}" class="font-serif font-semibold">{{ $r->title }}</a></li>@endforeach</noscript>
            </ul>
        </section>

        {{-- Secondary --}}
        <div class="lg:col-span-3 grid gap-6 content-start sm:grid-cols-2 lg:grid-cols-1 lg:border-r lg:border-[var(--rule)] lg:pr-8">
            @foreach ($secondary->take(2) as $s)
                <x-story :t="$s" variant="stack" :class="$loop->first ? '' : 'sm:border-0 lg:border-t lg:border-[var(--rule)] lg:pt-6'" />
            @endforeach
            @foreach ($secondary->slice(2) as $s)
                <x-story :t="$s" variant="mini" class="border-t border-[var(--rule)] pt-4" />
            @endforeach
        </div>

        {{-- Latest live stream --}}
        <aside class="hidden lg:block lg:col-span-3" x-data="latestStream" aria-label="{{ __('Latest') }}">
            <div class="flex items-center justify-between border-t-[3px] border-[var(--rule-strong)] pt-2.5 mb-3">
                <h2 class="flex items-center gap-2 text-[0.9375rem] font-extrabold">
                    <span class="live-dot text-[var(--live)]"></span>{{ __('Latest') }}
                </h2>
                <a href="{{ lroute('latest') }}" class="text-xs font-bold text-[var(--muted)] hover:text-[var(--fg)]">{{ __('See all') }}</a>
            </div>
            <ol class="timeline space-y-4">
                <template x-for="s in fresh" :key="s.id">
                    <li class="timeline-item is-new">
                        <p class="meta"><time :datetime="s.published_at" data-rel x-text="rel(s.published_at)"></time> <span x-show="s.category">· <span x-text="s.category"></span></span></p>
                        <a :href="s.url" class="headline headline-xs mt-0.5 block hover:underline" x-text="s.title"></a>
                    </li>
                </template>
                @foreach ($rail as $r)
                    <li class="timeline-item {{ $r->article->is_breaking ? 'is-key' : '' }}">
                        <p class="meta"><x-time :at="$r->published_at" />@if ($r->article->category) · {{ $r->article->category->nameFor($locale) }}@endif</p>
                        <a href="{{ $r->url() }}" class="headline headline-xs mt-0.5 block hover:underline">{{ $r->title }}</a>
                    </li>
                @endforeach
            </ol>
            <x-ad slot="sidebar" class="mt-8" />
        </aside>
    </div>
</section>

{{-- ── Live TV strip (only when a verified broadcast is on air) ────── --}}
@if ($tvChannel)
    <section class="wrap mt-10">
        <a href="{{ lroute('tv') }}" class="group flex items-center gap-4 rounded-sm bg-[var(--color-forest-900)] p-4 text-white">
            <span class="grid h-12 w-12 flex-none place-items-center rounded-full bg-[var(--color-alert-600)]"><svg class="h-5 w-5 translate-x-px" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-[#ff8a80]"><span class="live-dot"></span>{{ __('On air now') }}</p>
                <p class="truncate font-serif text-xl font-semibold">{{ $tvChannel->currentProgram()?->titleFor($locale) ?? $tvChannel->name }}</p>
            </div>
            <span class="ml-auto hidden sm:inline text-sm font-bold text-[var(--color-lime-400)] group-hover:underline">{{ __('Watch Live TV') }} →</span>
        </a>
    </section>
@endif

<x-ad slot="home_leaderboard" class="wrap mt-10" />

{{-- ── Editor-ordered modules ────────────────────────────────────── --}}
@foreach ($blocks as $block)
    @switch($block['type'])

        @case('live')
            <section class="mt-12 bg-[var(--bg-2)] py-10 border-y border-[var(--rule)]" aria-label="{{ __('Live coverage') }}">
                <div class="wrap">
                    <div class="section-head" style="border-color: var(--live)">
                        <h2 class="!text-[var(--live)]"><span class="live-dot"></span>&nbsp;{{ __('Live coverage') }}</h2>
                        <a href="{{ lroute('live') }}">{{ __('All live coverage') }} →</a>
                    </div>
                    <div class="grid gap-8 {{ count($block['items']) > 1 ? 'lg:grid-cols-2' : '' }}">
                        @foreach ($block['items'] as $ev)
                            <article class="grid gap-6 {{ count($block['items']) > 1 ? '' : 'md:grid-cols-2' }}">
                                <div>
                                    <a href="{{ $ev->url() }}" class="media-frame ratio-16x9 block mb-4 group">
                                        <x-img :media="$ev->cover" :width="960" />
                                        <span class="absolute left-3 top-3 badge badge-live"><span class="live-dot"></span>{{ __('Live') }}</span>
                                    </a>
                                    <a href="{{ $ev->url() }}"><h3 class="headline headline-lg hover:underline">{{ $ev->title }}</h3></a>
                                    @if ($ev->summary)<p class="dek mt-2 line-clamp-2">{{ $ev->summary }}</p>@endif
                                </div>
                                <ol class="timeline space-y-4 self-start">
                                    @foreach ($ev->updates as $u)
                                        <li class="timeline-item {{ $u->is_key_event ? 'is-key' : '' }}">
                                            <p class="meta"><x-time :at="$u->posted_at" class="text-[var(--live)] font-extrabold" /> @if ($u->is_pinned)· {{ __('Pinned') }}@endif</p>
                                            <a href="{{ $ev->url() }}#u-{{ $u->id }}" class="headline headline-xs mt-0.5 block hover:underline">{{ $u->headline ?: \Illuminate\Support\Str::limit(strip_tags($u->body), 140) }}</a>
                                        </li>
                                    @endforeach
                                    <li><a href="{{ $ev->url() }}" class="btn btn-ghost !py-2 text-xs">{{ __('Follow live') }} →</a></li>
                                </ol>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
            @break

        @case('latest')
            <section class="wrap mt-12">
                <x-section-head :title="__('More top stories')" :href="lroute('latest')" />
                <div class="grid gap-x-6 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($block['items']->take(4) as $s)
                        <x-story :t="$s" variant="stack" />
                    @endforeach
                </div>
                @if ($block['items']->count() > 4)
                    <div class="mt-8 grid gap-x-6 gap-y-5 border-t border-[var(--rule)] pt-6 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($block['items']->slice(4) as $s)
                            <x-story :t="$s" variant="mini" />
                        @endforeach
                    </div>
                @endif
            </section>
            @break

        @case('trending')
            <section class="wrap mt-12">
                <x-section-head :title="__('Trending now')" />
                <ol class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach ($block['items'] as $i => $s)
                        <li><x-story :t="$s" variant="list" :number="$i + 1" /></li>
                    @endforeach
                </ol>
            </section>
            @break

        @case('video')
            <section class="mt-14 bg-[var(--color-forest-950)] py-12 text-white" aria-label="{{ __('Video') }}">
                <div class="wrap">
                    <div class="section-head !border-white">
                        <h2 class="text-white">{{ __('Watch') }}</h2>
                        <a href="{{ lroute('videos') }}" class="!text-white/70 hover:!text-white">{{ __('All videos') }} →</a>
                    </div>
                    @php $main = $block['items']->first(); @endphp
                    <div class="grid gap-8 lg:grid-cols-12">
                        <a href="{{ $main->url() }}" class="group lg:col-span-8">
                            <div class="media-frame ratio-16x9">
                                @if ($main->thumbnailUrl())<img src="{{ $main->thumbnailUrl(1280) }}" alt="" loading="lazy">@else<x-img />@endif
                                <span class="absolute inset-0 grid place-items-center"><span class="grid h-16 w-16 place-items-center rounded-full bg-[var(--color-lime-400)] text-[var(--color-forest-950)] transition group-hover:scale-110"><svg class="h-7 w-7 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></span>
                                @if ($main->durationLabel())<span class="absolute bottom-3 right-3 rounded-sm bg-black/75 px-1.5 py-0.5 text-xs font-bold tabular-nums">{{ $main->durationLabel() }}</span>@endif
                            </div>
                            <p class="kicker mt-4 !text-[var(--color-lime-400)]">{{ __('video_kind.'.$main->kind) }}</p>
                            <h3 class="headline headline-lg mt-1 !text-white group-hover:underline">{{ $main->title }}</h3>
                            @if ($main->description)<p class="mt-2 max-w-2xl text-white/65 line-clamp-2">{{ $main->description }}</p>@endif
                        </a>
                        <div class="grid content-start gap-5 lg:col-span-4">
                            @foreach ($block['items']->slice(1) as $v)
                                <a href="{{ $v->url() }}" class="group grid grid-cols-[8.5rem_1fr] gap-3.5">
                                    <div class="media-frame ratio-16x9">
                                        @if ($v->thumbnailUrl())<img src="{{ $v->thumbnailUrl(480) }}" alt="" loading="lazy">@else<x-img />@endif
                                        @if ($v->durationLabel())<span class="absolute bottom-1.5 right-1.5 rounded-sm bg-black/75 px-1 text-[0.6875rem] font-bold tabular-nums">{{ $v->durationLabel() }}</span>@endif
                                    </div>
                                    <div>
                                        <p class="text-[0.625rem] font-extrabold uppercase tracking-wider text-[var(--color-lime-400)]">{{ __('video_kind.'.$v->kind) }}</p>
                                        <h3 class="mt-0.5 font-serif text-[1.0625rem] font-semibold leading-snug group-hover:underline">{{ $v->title }}</h3>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
            @break

        @case('category')
        @case('format')
            @php
                $href = $block['type'] === 'category'
                    ? ($block['category'] ? lroute('section', ['slug' => $block['category']->t($locale)?->slug ?? $block['category']->key]) : null)
                    : lroute('format', ['slug' => config("doogsan.formats.{$block['format']}.slug.{$locale}")]);
                $items = $block['items'];
            @endphp
            @php
                $n = $items->count();
                $style = $block['style'] ?? 'grid';
                if ($style === 'feature' && $n < 3) { $style = 'grid'; }
                $cols = [1 => '', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3'][$n] ?? 'sm:grid-cols-2 lg:grid-cols-4';
            @endphp
            @if ($style === 'columns')
                <section class="mt-14 py-12 bg-[var(--bg-2)]">
                    <div class="wrap">
                        <x-section-head :title="$block['title']" :href="$href" />
                        <div class="grid gap-x-8 gap-y-8 {{ $cols }}">
                            @foreach ($items as $s)
                                @php $au = $s->article->author; @endphp
                                <article class="group">
                                    <div class="flex items-center gap-3 mb-3">
                                        @if ($au?->photo)
                                            <img src="{{ $au->photo->url(480) }}" alt="" class="h-14 w-14 rounded-full object-cover grayscale group-hover:grayscale-0 transition" loading="lazy">
                                        @elseif ($au)
                                            <span class="grid h-14 w-14 place-items-center rounded-full bg-[var(--color-forest-900)] font-serif text-lg font-semibold text-[var(--color-lime-400)]">{{ $au->initials() }}</span>
                                        @endif
                                        @if ($au)<a href="{{ lroute('author', ['slug' => $au->slug]) }}" class="text-sm font-extrabold hover:underline">{{ $au->name }}</a>@endif
                                    </div>
                                    <a href="{{ $s->url() }}"><h3 class="headline headline-md italic"><span class="story-link">{{ $s->title }}</span></h3></a>
                                    <p class="meta mt-2"><x-time :at="$s->published_at" /></p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @elseif ($style === 'feature')
                <section class="wrap mt-14">
                    <x-section-head :title="$block['title']" :href="$href" />
                    <div class="grid gap-8 lg:grid-cols-12">
                        <x-story :t="$items->first()" variant="feature" class="lg:col-span-7" />
                        <div class="lg:col-span-5 divide-y divide-[var(--rule)] border-t border-[var(--rule)] lg:border-t-0">
                            @foreach ($items->slice(1) as $s)
                                <x-story :t="$s" variant="side" class="py-4 first:pt-4 lg:first:pt-0" />
                            @endforeach
                        </div>
                    </div>
                </section>
            @else
                <section class="wrap mt-14">
                    <x-section-head :title="$block['title']" :href="$href" />
                    @if ($n === 1)
                        <x-story :t="$items->first()" variant="wide" />
                    @else
                        <div class="grid gap-x-6 gap-y-8 {{ $cols }}">
                            @foreach ($items as $s)
                                <x-story :t="$s" :variant="$n <= 2 ? 'feature' : 'stack'" :show-dek="$n <= 3" />
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
            @break

        @case('columns')
            <section class="wrap mt-14">
                <div class="grid gap-x-8 gap-y-12 {{ [1 => '', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][count($block['items'])] }}">
                    @foreach ($block['items'] as $col)
                        @php
                            $href = $col['type'] === 'category' && $col['category']
                                ? lroute('section', ['slug' => $col['category']->t($locale)?->slug ?? $col['category']->key])
                                : ($col['type'] === 'format' ? lroute('format', ['slug' => config("doogsan.formats.{$col['format']}.slug.{$locale}")]) : null);
                        @endphp
                        <div>
                            <x-section-head :title="$col['title']" :href="$href" />
                            <x-story :t="$col['items']->first()" variant="stack" />
                            <div class="mt-5 space-y-4">
                                @foreach ($col['items']->slice(1) as $s)
                                    <x-story :t="$s" variant="mini" class="border-t border-[var(--rule)] pt-4" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            @break

        @case('visual')
            <section class="wrap mt-14">
                <x-section-head :title="__('Visual stories')" :href="lroute('galleries')" />
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($block['items'] as $s)
                        <x-story :t="$s" variant="overlay" />
                    @endforeach
                </div>
            </section>
            @break

        @case('ad')
            <x-ad :slot="$block['slot']" class="wrap mt-14" />
            @break

        @case('newsletter')
            <section class="wrap mt-14">
                <div class="grid gap-6 rounded-sm border border-[var(--rule)] bg-[var(--surface)] p-6 md:grid-cols-[1fr_auto] md:items-center md:p-8">
                    <div>
                        <p class="kicker">{{ __('Newsletter') }}</p>
                        <p class="headline headline-lg mt-1">{{ __('Start every morning with Doogsan.') }}</p>
                        <p class="dek mt-1 text-base">{{ __('The essential stories from Puntland, Somalia and the world — free, every weekday.') }}</p>
                    </div>
                    <form method="post" action="{{ lroute('newsletter.subscribe') }}" class="flex w-full min-w-0 flex-col gap-2 xs:flex-row md:w-[26rem]">
                        @csrf
                        <input type="hidden" name="source" value="home">
                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                        <input type="email" name="email" required class="field" placeholder="{{ __('Your email address') }}" aria-label="{{ __('Email address') }}">
                        <button class="btn btn-primary flex-none">{{ __('Subscribe') }}</button>
                    </form>
                </div>
            </section>
            @break
    @endswitch
@endforeach

@endif
@endsection
