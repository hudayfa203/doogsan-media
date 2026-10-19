@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--rule-strong)] pb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="kicker flex items-center gap-2"><span class="live-dot text-[var(--live)]"></span>{{ __('Updated continuously') }}</p>
            <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Latest News') }}</h1>
        </div>
        <div x-data class="text-xs font-bold text-[var(--muted)]">
            <span x-show="$store.live.mode === 'socket'">● {{ __('Live updates on') }}</span>
            <span x-show="$store.live.mode !== 'socket'" x-cloak>{{ __('Auto-refreshing') }}</span>
        </div>
    </header>

    <div class="grid gap-10 py-8 lg:grid-cols-[1fr_20rem]">
        <ol class="timeline space-y-8" x-data="latestStream">
            <template x-for="s in fresh" :key="s.id">
                <li class="timeline-item is-new">
                    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                        <div>
                            <p class="meta"><time :datetime="s.published_at" data-rel x-text="rel(s.published_at)"></time> <span x-show="s.category">· <span x-text="s.category"></span></span></p>
                            <a :href="s.url"><h2 class="headline headline-md mt-1 hover:underline" x-text="s.title"></h2></a>
                            <p class="dek mt-1.5 text-base line-clamp-2" x-text="s.standfirst"></p>
                        </div>
                        <template x-if="s.image"><img :src="s.image" alt="" class="hidden sm:block aspect-[3/2] w-full object-cover"></template>
                    </div>
                </li>
            </template>
            @forelse ($stories as $s)
                <li class="timeline-item {{ $s->article->is_breaking ? 'is-key' : '' }}">
                    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                        <div class="min-w-0">
                            <p class="meta"><x-time :at="$s->published_at" class="font-extrabold text-[var(--fg-2)]" /> · {{ news_date($s->published_at) }}@if ($s->article->category) · <span class="kicker">{{ $s->article->category->nameFor() }}</span>@endif</p>
                            <a href="{{ $s->url() }}"><h2 class="headline headline-md mt-1 hover:underline">{{ $s->title }}</h2></a>
                            @if ($s->standfirst)<p class="dek mt-1.5 text-base line-clamp-2">{{ $s->standfirst }}</p>@endif
                            @if ($s->article->author)<p class="meta mt-1.5">{{ $s->article->author->name }}</p>@endif
                        </div>
                        @if ($s->article->featuredMedia)
                            <a href="{{ $s->url() }}" class="media-frame ratio-3x2 hidden sm:block" tabindex="-1"><x-img :media="$s->article->featuredMedia" :width="480" /></a>
                        @endif
                    </div>
                </li>
            @empty
                <li class="text-[var(--muted)]">{{ __('No stories yet.') }}</li>
            @endforelse
        </ol>
        <aside class="space-y-8">
            <div x-data="notifyOptIn" x-show="supported && state !== 'granted'" class="rounded-sm border border-[var(--rule)] p-5">
                <p class="font-extrabold">{{ __('Breaking news alerts') }}</p>
                <p class="mt-1 text-sm text-[var(--muted)]">{{ __('Get a browser notification for major breaking news while Doogsan Media is open.') }}</p>
                <button @click="enable" class="btn btn-primary mt-3 !py-2 text-sm" x-show="state === 'default'">{{ __('Turn on alerts') }}</button>
                <p x-show="state === 'denied'" x-cloak class="mt-2 text-xs text-[var(--muted)]">{{ __('Notifications are blocked in your browser settings.') }}</p>
            </div>
            <x-ad slot="sidebar" />
        </aside>
    </div>
    {{ $stories->links() }}
</section>
@endsection
