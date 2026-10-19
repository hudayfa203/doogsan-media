@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="grid gap-6 border-b-[3px] border-[var(--rule-strong)] pb-8 md:grid-cols-[auto_1fr] md:items-center">
        @if ($author->photo)
            <img src="{{ $author->photo->url(480) }}" alt="{{ $author->name }}" class="h-32 w-32 rounded-full object-cover">
        @else
            <span class="grid h-32 w-32 place-items-center rounded-full bg-[var(--color-forest-900)] font-serif text-4xl font-semibold text-[var(--color-lime-400)]">{{ $author->initials() }}</span>
        @endif
        <div>
            <p class="kicker">{{ $author->desk }}</p>
            <h1 class="headline text-[2.2rem] md:text-[3rem] leading-none mt-1">{{ $author->name }}</h1>
            <p class="mt-2 font-bold text-[var(--muted)]">{{ $author->titleFor(edition()) }}</p>
            @if ($author->bioFor(edition()))<p class="dek mt-3 max-w-2xl">{{ $author->bioFor(edition()) }}</p>@endif
            <div class="mt-4 flex flex-wrap gap-3 text-sm font-bold">
                @if ($author->x_handle)<a href="https://x.com/{{ ltrim($author->x_handle, '@') }}" target="_blank" rel="noopener" class="hover:underline">@{{ ltrim($author->x_handle, '@') }}</a>@endif
                @if ($author->linkedin_url)<a href="{{ $author->linkedin_url }}" target="_blank" rel="noopener" class="hover:underline">LinkedIn</a>@endif
                @if ($author->email)<a href="mailto:{{ $author->email }}" class="hover:underline">{{ $author->email }}</a>@endif
            </div>
        </div>
    </header>
    <h2 class="mt-10 mb-6 text-sm font-extrabold uppercase tracking-[0.12em]">{{ __('Latest from :name', ['name' => $author->name]) }}</h2>
    @if ($stories->isEmpty())
        <p class="text-[var(--muted)]">{{ __('No stories yet.') }}</p>
    @else
        <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">@foreach ($stories as $s)<x-story :t="$s" variant="stack" />@endforeach</div>
        {{ $stories->links() }}
    @endif
</section>
@endsection
