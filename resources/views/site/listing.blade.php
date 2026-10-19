@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--rule-strong)] pb-5" @if (! empty($accent)) style="border-color: {{ $accent }}" @endif>
        <p class="kicker">{{ $kicker ?? '' }}</p>
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ $heading }}</h1>
        @if (! empty($intro))<p class="dek mt-3 max-w-2xl">{{ $intro }}</p>@endif
        @if (! empty($subsections) && $subsections->isNotEmpty())
            <nav class="mt-5 flex flex-wrap gap-2" aria-label="{{ __('Subsections') }}">
                @foreach ($subsections as $sub)
                    <a href="{{ lroute('section', ['slug' => $sub->t()?->slug ?? $sub->key]) }}" class="rounded-full border border-[var(--rule)] px-3 py-1 text-sm font-bold hover:border-[var(--fg)]">{{ $sub->nameFor() }}</a>
                @endforeach
            </nav>
        @endif
    </header>

    @if ($stories->isEmpty())
        <p class="py-20 text-center text-[var(--muted)]">{{ __('No stories here yet.') }}</p>
    @else
        @php $items = $stories->getCollection(); $first = $stories->onFirstPage(); @endphp
        @if ($first)
            <div class="grid gap-8 py-8 lg:grid-cols-12 border-b border-[var(--rule)]">
                <x-story :t="$items->first()" variant="feature" class="lg:col-span-7" :eager="true" />
                <div class="lg:col-span-5 grid gap-6 content-start sm:grid-cols-2 lg:grid-cols-1">
                    @foreach ($items->slice(1, 2) as $s)
                        <x-story :t="$s" variant="side" class="{{ $loop->first ? '' : 'border-t border-[var(--rule)] pt-6' }}" />
                    @endforeach
                </div>
            </div>
            <x-ad slot="home_leaderboard" class="mt-8" />
        @endif
        <div class="grid gap-x-6 gap-y-10 py-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($first ? $items->slice(3) : $items as $s)
                <x-story :t="$s" variant="stack" :show-dek="true" />
            @endforeach
        </div>
        {{ $stories->links() }}
    @endif
</section>
@endsection
