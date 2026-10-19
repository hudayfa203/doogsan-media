{{--
  Story card. Variants:
  lead     — dominant homepage lead (big image + XL serif headline + dek)
  feature  — image on top, large headline, dek
  stack    — image on top, medium headline (grids)
  side     — small image right, headline left (rails)
  list     — numbered/plain headline rows with meta
  mini     — headline only + time
  overlay  — headline over image (visual stories)
--}}
@props(['t', 'variant' => 'stack', 'showDek' => null, 'number' => null, 'imageRatio' => null, 'eager' => false])
@php
    $a = $t->article;
    $locale = $t->locale;
    $img = $a->featuredMedia;
    $cat = $a->category;
    $isLive = $a->live_event_id && $a->liveEvent?->status === 'live';
    $showDek ??= in_array($variant, ['lead', 'feature']);
    $ratio = $imageRatio ?? match ($variant) { 'lead' => 'ratio-3x2', 'side' => 'ratio-3x2', 'overlay' => 'ratio-4x5', default => 'ratio-16x9' };
    $width = match ($variant) { 'lead' => 1600, 'feature' => 960, 'side' => 480, default => 960 };
    $label = $a->format !== 'news' ? format_label($a->format, $locale) : $cat?->nameFor($locale);
@endphp

@if ($variant === 'overlay')
    <article {{ $attributes->class('group relative overflow-hidden bg-[var(--color-forest-950)]') }}>
        <a href="{{ $t->url() }}" class="block">
            <div class="media-frame {{ $ratio }}">
                <x-img :media="$img" :width="$width" class="opacity-90" :eager="$eager" />
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-5">
                <span class="badge badge-format mb-2">{{ format_label($a->format, $locale) }}</span>
                <h3 class="headline headline-md text-white"><span class="story-link">{{ $t->title }}</span></h3>
                @if ($a->gallery_count ?? $a->gallery()->count())
                    <p class="mt-2 text-xs font-semibold text-white/70">{{ trans_choice('gallery.photos', $a->gallery()->count(), ['n' => $a->gallery()->count()]) }}</p>
                @endif
            </div>
        </a>
    </article>
@elseif ($variant === 'wide')
    <article {{ $attributes->class('group grid gap-6 md:grid-cols-12 md:items-center') }}>
        <a href="{{ $t->url() }}" class="media-frame ratio-16x9 block md:col-span-7" tabindex="-1" aria-hidden="true">
            <x-img :media="$img" :width="1600" sizes="(min-width:768px) 58vw, 100vw" />
        </a>
        <div class="md:col-span-5">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                @if ($a->format !== 'news')<span class="badge badge-format">{{ format_label($a->format, $locale) }}</span>@endif
                @if ($cat)<span class="kicker">{{ $cat->nameFor($locale) }}</span>@endif
                @if ($a->is_sample)<span class="badge badge-sample">{{ __('Sample') }}</span>@endif
            </div>
            <a href="{{ $t->url() }}"><h3 class="headline headline-lg"><span class="story-link">{{ $t->title }}</span></h3></a>
            @if ($t->standfirst)<p class="dek mt-3 line-clamp-3">{{ $t->standfirst }}</p>@endif
            <p class="meta mt-3">@if ($a->author){{ $a->author->name }} · @endif<x-time :at="$t->published_at" /></p>
        </div>
    </article>
@elseif ($variant === 'mini')
    <article {{ $attributes->class('group') }}>
        <a href="{{ $t->url() }}" class="block">
            <h3 class="headline headline-xs"><span class="story-link">{{ $t->title }}</span></h3>
        </a>
        <p class="meta mt-1"><x-time :at="$t->published_at" /></p>
    </article>
@elseif ($variant === 'list')
    <article {{ $attributes->class('group flex gap-4') }}>
        @if ($number)
            <span class="font-serif text-3xl font-semibold leading-none text-[var(--accent)] tabular-nums w-7 flex-none">{{ $number }}</span>
        @endif
        <div class="min-w-0">
            @if ($label)<p class="kicker mb-1">{{ $label }}</p>@endif
            <a href="{{ $t->url() }}"><h3 class="headline headline-sm"><span class="story-link">{{ $t->title }}</span></h3></a>
            <p class="meta mt-1.5 flex items-center gap-2">
                @if ($isLive)<span class="badge badge-live"><span class="live-dot"></span>{{ __('Live') }}</span>@endif
                <x-time :at="$t->published_at" />
                @if (isset($t->readers))<span>· {{ trans_choice('analytics.readers', $t->readers, ['n' => number_format($t->readers)]) }}</span>@endif
            </p>
        </div>
    </article>
@elseif ($variant === 'side')
    <article {{ $attributes->class('group grid grid-cols-[1fr_6.5rem] sm:grid-cols-[1fr_8rem] gap-3.5') }}>
        <div class="min-w-0">
            @if ($label)<p class="kicker mb-1">{{ $label }}</p>@endif
            <a href="{{ $t->url() }}"><h3 class="headline headline-xs"><span class="story-link">{{ $t->title }}</span></h3></a>
            <p class="meta mt-1.5"><x-time :at="$t->published_at" /></p>
        </div>
        <a href="{{ $t->url() }}" class="media-frame {{ $ratio }} self-start" tabindex="-1" aria-hidden="true">
            <x-img :media="$img" :width="480" />
        </a>
    </article>
@else
    <article {{ $attributes->class('group') }}>
        <a href="{{ $t->url() }}" class="media-frame {{ $ratio }} block mb-3.5" tabindex="-1" aria-hidden="true">
            <x-img :media="$img" :width="$width" :eager="$eager" :sizes="$variant === 'lead' ? '(min-width:1024px) 60vw, 100vw' : '(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw'" />
            @if ($isLive || $a->is_breaking)
                <span class="absolute left-3 top-3 badge {{ $isLive ? 'badge-live' : 'badge-breaking' }}"><span class="live-dot"></span>{{ $isLive ? __('Live') : __('Breaking') }}</span>
            @endif
            @if ($a->video_url)
                <span class="absolute bottom-3 left-3 grid h-10 w-10 place-items-center rounded-full bg-black/70 text-white"><svg class="h-4 w-4 translate-x-px" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
            @endif
        </a>
        <div class="flex items-center gap-2 mb-1.5">
            @if ($a->format !== 'news')<span class="badge badge-format">{{ format_label($a->format, $locale) }}</span>@endif
            @if ($cat)<a href="{{ lroute('section', ['slug' => $cat->t($locale)?->slug ?? $cat->key], $locale) }}" class="kicker hover:underline">{{ $cat->nameFor($locale) }}</a>@endif
            @if ($a->is_sample)<span class="badge badge-sample" title="{{ __('Sample content for review — not real news') }}">{{ __('Sample') }}</span>@endif
        </div>
        <a href="{{ $t->url() }}">
            <h3 class="headline {{ match ($variant) { 'lead' => 'headline-xl', 'feature' => 'headline-lg', default => 'headline-md' } }}"><span class="story-link">{{ $t->title }}</span></h3>
        </a>
        @if ($showDek && $t->standfirst)
            <p class="dek mt-2.5 {{ $variant === 'lead' ? 'text-lg md:text-[1.25rem] line-clamp-3' : 'line-clamp-3' }}">{{ $t->standfirst }}</p>
        @endif
        <p class="meta mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1">
            @if ($a->author)<a href="{{ lroute('author', ['slug' => $a->author->slug], $locale) }}" class="text-[var(--fg-2)] hover:underline">{{ $a->author->name }}</a><span aria-hidden="true">·</span>@endif
            <x-time :at="$t->published_at" />
            @if ($t->wasUpdated())<span class="text-[var(--accent)]">· {{ __('Updated') }}</span>@endif
        </p>
    </article>
@endif
