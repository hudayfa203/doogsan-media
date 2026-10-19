@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--rule-strong)] pb-5">
        <p class="kicker">{{ __('Watch & Listen') }}</p>
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Media Centre') }}</h1>
        <nav class="mt-5 flex flex-wrap gap-2">
            @foreach (['tv' => __('Live TV'), 'videos' => __('Videos'), 'documentaries' => __('Documentaries'), 'podcasts' => __('Podcasts'), 'galleries' => __('Photo Gallery')] as $r => $label)
                <a href="{{ lroute($r) }}" class="rounded-full border border-[var(--rule)] px-4 py-1.5 text-sm font-bold hover:border-[var(--fg)]">{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    @if ($featured->isNotEmpty())
        <div class="grid gap-8 py-10 lg:grid-cols-12">
            @php $main = $featured->first(); @endphp
            <a href="{{ $main->url() }}" class="group lg:col-span-8">
                <div class="media-frame ratio-16x9">
                    @if ($main->thumbnailUrl())<img src="{{ $main->thumbnailUrl(1600) }}" alt="" loading="eager">@else<x-img />@endif
                    <span class="absolute inset-0 grid place-items-center"><span class="grid h-16 w-16 place-items-center rounded-full bg-[var(--color-lime-400)] text-[var(--color-forest-950)]"><svg class="h-7 w-7 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></span>
                </div>
                <p class="kicker mt-4">{{ __('video_kind.'.$main->kind) }}</p>
                <h2 class="headline headline-lg mt-1 group-hover:underline">{{ $main->title }}</h2>
            </a>
            <div class="grid content-start gap-6 lg:col-span-4">
                @foreach ($featured->slice(1, 3) as $v)
                    @include('site.partials.video-card', ['v' => $v])
                @endforeach
            </div>
        </div>
    @endif

    @if ($documentaries->isNotEmpty())
        <x-section-head :title="__('Documentaries')" :href="lroute('documentaries')" />
        <div class="grid gap-6 sm:grid-cols-3 mb-12">@foreach ($documentaries as $v)@include('site.partials.video-card', ['v' => $v])@endforeach</div>
    @endif
    @if ($podcasts->isNotEmpty())
        <x-section-head :title="__('Podcasts')" :href="lroute('podcasts')" />
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-12">@foreach ($podcasts as $v)@include('site.partials.video-card', ['v' => $v])@endforeach</div>
    @endif
    @if ($galleries->isNotEmpty())
        <x-section-head :title="__('Photo Gallery')" :href="lroute('galleries')" />
        <div class="grid gap-4 sm:grid-cols-3">@foreach ($galleries as $s)<x-story :t="$s" variant="overlay" />@endforeach</div>
    @endif
</section>
@endsection
