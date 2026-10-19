@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--rule-strong)] pb-5">
        <p class="kicker">Doogsan Media</p>
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ $title }}</h1>
    </header>
    <div class="grid gap-x-6 gap-y-10 py-10 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($authors as $au)
            <a href="{{ lroute('author', ['slug' => $au->slug]) }}" class="group flex gap-4 items-start">
                @if ($au->photo)
                    <img src="{{ $au->photo->url(480) }}" alt="" class="h-16 w-16 flex-none rounded-full object-cover">
                @else
                    <span class="grid h-16 w-16 flex-none place-items-center rounded-full bg-[var(--color-forest-900)] font-serif text-xl font-semibold text-[var(--color-lime-400)]">{{ $au->initials() }}</span>
                @endif
                <div>
                    <p class="font-extrabold group-hover:underline">{{ $au->name }}</p>
                    <p class="text-sm text-[var(--muted)]">{{ $au->titleFor(edition()) }}</p>
                    <p class="meta mt-1">{{ trans_choice('people.stories', $au->articles_count, ['n' => $au->articles_count]) }}</p>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endsection
