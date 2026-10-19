@extends('layouts.site')

@section('content')
<section class="bg-[var(--color-forest-950)] text-white">
    <div class="wrap py-8 md:py-10">
        <div class="mx-auto max-w-5xl">
            <div class="relative ratio-16x9 bg-black overflow-hidden">
                @php $src = $video->playerUrl(); @endphp
                @if ($src && $video->provider === 'youtube')
                    <iframe src="{{ $src }}" class="absolute inset-0 h-full w-full" title="{{ $video->title }}" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                @elseif ($src && $video->provider === 'audio')
                    @if ($video->thumbnailUrl())<img src="{{ $video->thumbnailUrl(1600) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-50">@endif
                    <div class="absolute inset-x-0 bottom-0 p-6"><audio src="{{ $src }}" controls class="w-full"></audio></div>
                @elseif ($src)
                    <div x-data="tvPlayer(@js($src), @js($video->provider === 'hls' ? 'hls' : 'file'))" class="absolute inset-0">
                        <video x-ref="video" class="h-full w-full" controls playsinline preload="metadata" poster="{{ $video->thumbnailUrl(1600) }}" @if ($video->provider !== 'hls') src="{{ $src }}" @endif></video>
                    </div>
                @else
                    @if ($video->thumbnailUrl())<img src="{{ $video->thumbnailUrl(1600) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">@endif
                    <div class="absolute inset-0 grid place-items-center p-6 text-center">
                        <p class="max-w-md font-semibold">{{ __('This media item has no video file attached yet.') }}</p>
                    </div>
                @endif
            </div>
            <p class="kicker mt-6 !text-[var(--color-lime-400)]">{{ __('video_kind.'.$video->kind) }} @if ($video->durationLabel())· {{ $video->durationLabel() }}@endif</p>
            <h1 class="headline headline-xl !text-white mt-2">{{ $video->title }}</h1>
            @if ($video->description)<p class="mt-4 max-w-3xl font-serif text-lg text-white/75">{{ $video->description }}</p>@endif
            <p class="mt-4 text-sm text-white/50">@if ($video->author){{ $video->author->name }} · @endif{{ news_date($video->published_at) }}</p>
        </div>
    </div>
</section>
@if ($more->isNotEmpty())
    <section class="wrap mt-12">
        <x-section-head :title="__('More to watch')" :href="lroute('videos')" />
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach ($more as $v)@include('site.partials.video-card', ['v' => $v])@endforeach</div>
    </section>
@endif
@endsection
