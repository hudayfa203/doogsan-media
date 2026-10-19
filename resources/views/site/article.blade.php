@extends('layouts.site')

@section('content')
@php
    $locale = $t->locale;
    $cat = $a->category;
    $img = $a->featuredMedia;
    $isPreview = ! $t->isLive();
    $live = $a->liveEvent && $a->liveEvent->status === 'live' ? $a->liveEvent : null;
@endphp

@if ($isPreview)
    <div class="bg-amber-100 text-amber-900 text-sm font-bold no-print">
        <div class="wrap py-2">{{ __('Preview') }} — {{ __('status.'.$t->status) }}. {{ __('This version is not public.') }}</div>
    </div>
@endif

<article class="pb-6" itemscope itemtype="https://schema.org/NewsArticle">
    {{-- Header --}}
    <header class="wrap pt-6 md:pt-10">
        <div class="mx-auto max-w-[46rem]">
            <nav class="flex flex-wrap items-center gap-2 text-xs font-bold text-[var(--muted)]" aria-label="{{ __('Breadcrumb') }}">
                <a href="{{ lroute('home') }}" class="hover:text-[var(--fg)]">{{ __('Home') }}</a>
                @if ($cat)
                    <span aria-hidden="true">/</span>
                    <a href="{{ lroute('section', ['slug' => $cat->t($locale)?->slug ?? $cat->key]) }}" class="kicker hover:underline">{{ $cat->nameFor($locale) }}</a>
                @endif
            </nav>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($live)<a href="{{ $live->url() }}" class="badge badge-live"><span class="live-dot"></span>{{ __('Live') }}</a>@endif
                @if ($a->is_breaking)<span class="badge badge-breaking">{{ __('Breaking') }}</span>@endif
                @if ($a->format !== 'news')<span class="badge badge-format">{{ format_label($a->format) }}</span>@endif
                @if ($a->is_sample)<span class="badge badge-sample">{{ __('Sample content for review — not real news') }}</span>@endif
            </div>

            <h1 class="headline headline-xl mt-3 {{ $a->format === 'opinion' ? 'italic' : '' }}" itemprop="headline">{{ $t->title }}</h1>
            @if ($t->standfirst)
                <p class="dek mt-4 text-[1.25rem] md:text-[1.375rem] leading-snug" itemprop="description">{{ $t->standfirst }}</p>
            @endif

            {{-- Byline --}}
            <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-4 border-y border-[var(--rule)] py-4">
                <div class="flex items-center gap-3">
                    <div class="flex -space-x-2">
                        @foreach ($authors as $au)
                            @if ($au->photo)
                                <img src="{{ $au->photo->url(480) }}" alt="" class="h-11 w-11 rounded-full border-2 border-[var(--bg)] object-cover">
                            @else
                                <span class="grid h-11 w-11 place-items-center rounded-full border-2 border-[var(--bg)] bg-[var(--color-forest-900)] text-sm font-bold text-[var(--color-lime-400)]">{{ $au->initials() }}</span>
                            @endif
                        @endforeach
                    </div>
                    <div class="text-sm">
                        <p class="font-extrabold">
                            @foreach ($authors as $au)
                                <a href="{{ lroute('author', ['slug' => $au->slug]) }}" class="hover:underline" itemprop="author">{{ $au->name }}</a>@if (! $loop->last), @endif
                            @endforeach
                        </p>
                        <p class="text-[var(--muted)]">{{ $authors->first()?->titleFor($locale) }}@if ($a->location) · {{ $a->location }}@endif</p>
                    </div>
                </div>
                <div class="text-xs leading-relaxed text-[var(--muted)]">
                    <p>{{ __('Published') }} <time datetime="{{ $t->published_at?->toIso8601String() }}" itemprop="datePublished">{{ news_date($t->published_at) }}</time></p>
                    @if ($t->wasUpdated())
                        <p class="font-bold text-[var(--accent)]">{{ __('Updated') }} <time datetime="{{ $t->content_updated_at->toIso8601String() }}" itemprop="dateModified">{{ news_date($t->content_updated_at) }}</time></p>
                    @endif
                </div>
                <p class="text-xs font-bold text-[var(--muted)] flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    {{ trans_choice('time.read', $t->reading_minutes, ['n' => $t->reading_minutes]) }}
                </p>
                <div class="ml-auto flex items-center gap-1 no-print" x-data="share(@js($t->url()), @js($t->title))">
                    <a href="https://wa.me/?text={{ urlencode($t->title.' '.$t->url()) }}" target="_blank" rel="noopener" class="grid h-9 w-9 place-items-center rounded-full hover:bg-[var(--bg-2)]" aria-label="WhatsApp"><x-social-icon network="whatsapp" class="h-4 w-4" /></a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($t->url()) }}" target="_blank" rel="noopener" class="grid h-9 w-9 place-items-center rounded-full hover:bg-[var(--bg-2)]" aria-label="Facebook"><x-social-icon network="facebook" class="h-4 w-4" /></a>
                    <a href="https://x.com/intent/post?url={{ urlencode($t->url()) }}&text={{ urlencode($t->title) }}" target="_blank" rel="noopener" class="grid h-9 w-9 place-items-center rounded-full hover:bg-[var(--bg-2)]" aria-label="X"><x-social-icon network="x" class="h-4 w-4" /></a>
                    <button @click="copy" class="grid h-9 w-9 place-items-center rounded-full hover:bg-[var(--bg-2)]" :aria-label="copied ? '{{ __('Link copied') }}' : '{{ __('Copy link') }}'">
                        <svg x-show="!copied" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
                        <svg x-show="copied" x-cloak class="h-4 w-4 text-[var(--accent)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 5 5 9-10"/></svg>
                    </button>
                    <a href="{{ lroute('article.print', ['slug' => $t->slug]) }}" class="grid h-9 w-9 place-items-center rounded-full hover:bg-[var(--bg-2)]" aria-label="{{ __('Print') }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V3h12v6M6 18H4v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6h-2M6 14h12v7H6z"/></svg></a>
                    <button x-show="canNative" @click="native" class="grid h-9 w-9 place-items-center rounded-full hover:bg-[var(--bg-2)] md:hidden" aria-label="{{ __('Share') }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7M16 6l-4-4-4 4M12 2v13"/></svg></button>
                </div>
            </div>

            {{-- Language switch for this story --}}
            @php $otherLocale = $locale === 'en' ? 'so' : 'en'; @endphp
            @if (isset($alternates[$otherLocale]))
                <a href="{{ $alternates[$otherLocale] }}" hreflang="{{ $otherLocale }}" class="mt-3 inline-flex items-center gap-2 text-sm font-bold text-[var(--accent)] hover:underline no-print">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h8M8 3v2m1.5 9C8 12.5 7 10.5 6.5 8M3 14c2.5-1.5 4.5-4 5.5-9M13 21l4-10 4 10m-1.3-3h-5.4"/></svg>
                    {{ $otherLocale === 'so' ? 'Ku akhri Soomaali' : 'Read in English' }}
                </a>
            @endif
        </div>
    </header>

    {{-- Hero media --}}
    @if ($a->video_url && preg_match('~(?:v=|youtu\.be/|embed/)([A-Za-z0-9_-]{11})~', $a->video_url, $vm))
        <figure class="wrap mt-8"><div class="mx-auto max-w-5xl ratio-16x9 bg-black"><iframe class="h-full w-full" src="https://www.youtube-nocookie.com/embed/{{ $vm[1] }}" title="{{ $t->title }}" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe></div></figure>
    @elseif ($img)
        <figure class="mt-8 {{ $a->format === 'gallery' || $a->format === 'in-depth' ? '' : 'wrap' }}">
            <div class="mx-auto {{ $a->format === 'gallery' || $a->format === 'in-depth' ? '' : 'max-w-5xl' }}">
                <x-img :media="$img" :width="1600" :eager="true" sizes="(min-width: 1024px) 1024px, 100vw" class="w-full h-auto cursor-zoom-in" data-zoom />
            </div>
            @if ($img->captionFor($locale) || $img->credit)
                <figcaption class="figcap wrap mx-auto max-w-5xl">{{ $img->captionFor($locale) }} @if ($img->credit)<span class="credit">{{ $img->credit }}</span>@endif</figcaption>
            @endif
        </figure>
    @endif

    {{-- Body + rail --}}
    <div class="wrap mt-8 md:mt-10">
        <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[minmax(0,46rem)_1fr]">
            <div class="min-w-0">
                @if ($live)
                    <a href="{{ $live->url() }}" class="mb-8 flex items-center gap-3 rounded-sm border border-[var(--live)] p-4 no-print">
                        <span class="badge badge-live"><span class="live-dot"></span>{{ __('Live') }}</span>
                        <span class="font-bold">{{ __('Follow the latest developments in our live coverage') }} →</span>
                    </a>
                @endif

                @if (count($toc) >= 3)
                    <nav x-data="{ open: false }" class="mb-8 border-l-[3px] border-[var(--accent-bright)] bg-[var(--bg-2)] p-4 no-print" aria-label="{{ __('In this report') }}">
                        <button @click="open = !open" class="flex w-full items-center justify-between text-sm font-extrabold">
                            {{ __('In this report') }}
                            <svg :class="open && 'rotate-180'" class="h-4 w-4 transition" viewBox="0 0 20 20" fill="currentColor"><path d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z"/></svg>
                        </button>
                        <ol x-show="open" class="mt-3 space-y-2 text-sm list-decimal pl-5 marker:text-[var(--muted)] marker:font-bold" x-cloak>
                            @foreach ($toc as $h)
                                <li class="{{ $h['level'] === 3 ? 'ml-4' : '' }}"><a href="#{{ $h['id'] }}" class="hover:underline">{{ $h['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </nav>
                @endif

                <div class="story-body" itemprop="articleBody">
                    {!! $t->body_html !!}
                </div>

                @if ($a->audio_url)
                    <div class="mt-8 rounded-sm bg-[var(--bg-2)] p-4 no-print">
                        <p class="kicker mb-2">{{ __('Listen to this story') }}</p>
                        <audio controls preload="none" class="w-full" src="{{ $a->audio_url }}"></audio>
                    </div>
                @endif

                {{-- Gallery --}}
                @if ($a->gallery->isNotEmpty())
                    <section class="mt-10" aria-label="{{ __('Photo gallery') }}">
                        <x-section-head :title="__('Gallery')" />
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($a->gallery as $g)
                                <figure class="{{ $loop->first ? 'col-span-2 row-span-2' : '' }}">
                                    <img src="{{ $g->url($loop->first ? 1600 : 480) }}" alt="{{ $g->altFor($locale) }}" loading="lazy" data-zoom class="aspect-[4/3] w-full cursor-zoom-in object-cover">
                                    <figcaption class="sr-only">{{ $g->pivot->{'caption_'.$locale} ?: $g->captionFor($locale) }} {{ $g->credit }}</figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Corrections & updates --}}
                @if ($t->corrections->isNotEmpty())
                    <aside class="mt-10 border-t-[3px] border-[var(--rule-strong)] pt-4">
                        <h2 class="text-sm font-extrabold">{{ __('Corrections and updates') }}</h2>
                        <ul class="mt-3 space-y-3 text-sm">
                            @foreach ($t->corrections as $c)
                                <li><span class="font-bold uppercase text-xs tracking-wider {{ $c->kind === 'correction' ? 'text-[var(--live)]' : 'text-[var(--accent)]' }}">{{ __('correction.'.$c->kind) }}</span> · <span class="text-[var(--muted)]">{{ news_date($c->published_at) }}</span><br>{{ $c->note }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-[var(--muted)]">{{ __('Spotted an error?') }} <a href="{{ lroute('contact') }}?department=corrections" class="underline">{{ __('Tell our corrections desk') }}</a>.</p>
                    </aside>
                @endif

                {{-- Tags --}}
                @if ($t->tags->isNotEmpty())
                    <div class="mt-10 flex flex-wrap gap-2">
                        @foreach ($t->tags as $tag)
                            <a href="{{ lroute('tag', ['slug' => $tag->slug]) }}" class="rounded-full border border-[var(--rule)] px-3 py-1 text-xs font-bold hover:border-[var(--fg)]">#{{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif

                {{-- Author box --}}
                @foreach ($authors as $au)
                    <div class="mt-10 flex gap-4 border-t border-[var(--rule)] pt-6">
                        @if ($au->photo)<img src="{{ $au->photo->url(480) }}" alt="" class="h-16 w-16 flex-none rounded-full object-cover">@endif
                        <div>
                            <a href="{{ lroute('author', ['slug' => $au->slug]) }}" class="font-extrabold hover:underline">{{ $au->name }}</a>
                            <p class="text-sm text-[var(--muted)]">{{ $au->titleFor($locale) }}</p>
                            @if ($au->bioFor($locale))<p class="mt-2 text-sm leading-relaxed text-[var(--fg-2)]">{{ \Illuminate\Support\Str::limit($au->bioFor($locale), 260) }}</p>@endif
                        </div>
                    </div>
                @endforeach

                <x-ad slot="article_inline" class="mt-10" />
            </div>

            {{-- Rail --}}
            <aside class="no-print lg:pl-4">
                <div class="lg:sticky lg:top-36 space-y-10">
                    <div>
                        <div class="section-head"><h2>{{ __('Latest') }}</h2></div>
                        <div class="space-y-4">
                            @foreach ($latest->take(5) as $l)
                                <x-story :t="$l" variant="mini" class="{{ $loop->first ? '' : 'border-t border-[var(--rule)] pt-4' }}" />
                            @endforeach
                        </div>
                    </div>
                    <x-ad slot="sidebar" />
                    <div class="rounded-sm bg-[var(--color-forest-900)] p-5 text-white">
                        <p class="font-serif text-xl font-semibold">{{ __('Have a story we should investigate?') }}</p>
                        <p class="mt-1 text-sm text-white/70">{{ __('Send it to our editors securely. You can stay anonymous.') }}</p>
                        <a href="{{ lroute('tip') }}" class="btn mt-4 bg-[var(--color-lime-400)] text-[var(--color-forest-950)] !py-2 text-sm">{{ __('Submit a tip') }}</a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</article>

{{-- Related --}}
@if ($related->isNotEmpty())
    <section class="wrap mt-10 no-print">
        <x-section-head :title="__('Related coverage')" />
        <div class="grid gap-x-6 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($related->take(4) as $r)
                <x-story :t="$r" variant="stack" />
            @endforeach
        </div>
    </section>
@endif
@endsection
