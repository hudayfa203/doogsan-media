@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--rule-strong)] pb-5">
        <p class="kicker">Doogsan Media</p>
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Editorial Team') }}</h1>
        <p class="dek mt-3 max-w-2xl">{{ __('The editors, reporters and producers behind Doogsan Media’s journalism in English and Somali.') }}</p>
    </header>
    @foreach ($desks as $desk => $people)
        <h2 class="mt-10 mb-5 text-sm font-extrabold uppercase tracking-[0.12em] text-[var(--kicker)]">{{ $desk }}</h2>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($people as $au)
                <article class="border-t border-[var(--rule)] pt-5">
                    <div class="flex items-center gap-4">
                        @if ($au->photo)<img src="{{ $au->photo->url(480) }}" alt="" class="h-20 w-20 rounded-full object-cover">@else<span class="grid h-20 w-20 place-items-center rounded-full bg-[var(--color-forest-900)] font-serif text-2xl font-semibold text-[var(--color-lime-400)]">{{ $au->initials() }}</span>@endif
                        <div>
                            <a href="{{ lroute('author', ['slug' => $au->slug]) }}" class="text-lg font-extrabold hover:underline">{{ $au->name }}</a>
                            <p class="text-sm text-[var(--muted)]">{{ $au->titleFor(edition()) }}</p>
                        </div>
                    </div>
                    @if ($au->bioFor(edition()))<p class="mt-3 text-sm leading-relaxed text-[var(--fg-2)]">{{ $au->bioFor(edition()) }}</p>@endif
                </article>
            @endforeach
        </div>
    @endforeach
</section>
@endsection
