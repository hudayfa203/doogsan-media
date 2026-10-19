<a href="{{ $v->url() }}" class="group block">
    <div class="media-frame ratio-16x9">
        @if ($v->thumbnailUrl())<img src="{{ $v->thumbnailUrl(960) }}" alt="" loading="lazy">@else<x-img />@endif
        <span class="absolute bottom-3 left-3 grid h-10 w-10 place-items-center rounded-full bg-[var(--color-lime-400)] text-[var(--color-forest-950)]">
            @if ($v->kind === 'podcast')
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Zm7 9a7 7 0 0 1-14 0H3a9 9 0 0 0 8 8.9V22h2v-2.1a9 9 0 0 0 8-8.9h-2Z"/></svg>
            @else
                <svg class="h-4 w-4 translate-x-px" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            @endif
        </span>
        @if ($v->durationLabel())<span class="absolute bottom-3 right-3 rounded-sm bg-black/75 px-1.5 py-0.5 text-xs font-bold tabular-nums text-white">{{ $v->durationLabel() }}</span>@endif
    </div>
    <p class="kicker mt-3">{{ __('video_kind.'.$v->kind) }} @if ($v->is_sample)<span class="badge badge-sample ml-1">{{ __('Sample') }}</span>@endif</p>
    <h3 class="headline headline-sm mt-1 group-hover:underline">{{ $v->title }}</h3>
    <p class="meta mt-1"><x-time :at="$v->published_at" /></p>
</a>
