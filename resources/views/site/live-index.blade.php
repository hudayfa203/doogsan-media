@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--live)] pb-5">
        <p class="kicker !text-[var(--live)] flex items-center gap-2"><span class="live-dot"></span>{{ __('Live') }}</p>
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Live Coverage') }}</h1>
        <p class="dek mt-3 max-w-2xl">{{ __('Minute-by-minute reporting from the Doogsan Media newsroom.') }}</p>
    </header>

    @if ($active->isEmpty())
        <p class="py-12 text-[var(--muted)]">{{ __('There is no live coverage running right now.') }}</p>
    @endif
    <div class="grid gap-10 py-10">
        @foreach ($active as $ev)
            <article class="grid gap-8 lg:grid-cols-12 border-b border-[var(--rule)] pb-10">
                <a href="{{ $ev->url() }}" class="media-frame ratio-16x9 block lg:col-span-7 group">
                    <x-img :media="$ev->cover" :width="1600" />
                    <span class="absolute left-4 top-4 badge badge-live"><span class="live-dot"></span>{{ __('Live') }}</span>
                </a>
                <div class="lg:col-span-5">
                    <p class="meta">{{ __('Started') }} <x-time :at="$ev->starts_at" /></p>
                    <a href="{{ $ev->url() }}"><h2 class="headline headline-lg mt-1 hover:underline">{{ $ev->title }}</h2></a>
                    @if ($ev->summary)<p class="dek mt-2">{{ $ev->summary }}</p>@endif
                    <ol class="timeline mt-6 space-y-4">
                        @foreach ($ev->updates as $u)
                            <li class="timeline-item {{ $u->is_key_event ? 'is-key' : '' }}">
                                <p class="meta"><x-time :at="$u->posted_at" class="font-extrabold text-[var(--live)]" /></p>
                                <p class="headline headline-xs mt-0.5">{{ $u->headline ?: \Illuminate\Support\Str::limit(strip_tags($u->body), 140) }}</p>
                            </li>
                        @endforeach
                    </ol>
                    <a href="{{ $ev->url() }}" class="btn btn-primary mt-6">{{ __('Follow live') }}</a>
                </div>
            </article>
        @endforeach
    </div>

    @if ($upcoming->isNotEmpty())
        <x-section-head :title="__('Coming up')" />
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-12">
            @foreach ($upcoming as $ev)
                <li class="border border-[var(--rule)] p-4">
                    <p class="meta">{{ news_date($ev->starts_at) }}</p>
                    <p class="headline headline-sm mt-1">{{ $ev->title }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($past->isNotEmpty())
        <x-section-head :title="__('Earlier live coverage')" />
        <div class="grid gap-x-6 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($past as $ev)
                <a href="{{ $ev->url() }}" class="group">
                    <div class="media-frame ratio-16x9 mb-3"><x-img :media="$ev->cover" :width="960" /></div>
                    <p class="meta">{{ news_date($ev->ended_at ?? $ev->starts_at, false) }}</p>
                    <h3 class="headline headline-md mt-1 group-hover:underline">{{ $ev->title }}</h3>
                </a>
            @endforeach
        </div>
        {{ $past->links() }}
    @endif
</section>
@endsection
