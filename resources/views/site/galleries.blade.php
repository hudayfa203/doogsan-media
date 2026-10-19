@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--rule-strong)] pb-5">
        <p class="kicker"><a href="{{ lroute('media') }}" class="hover:underline">{{ __('Media Centre') }}</a></p>
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Photo Gallery') }}</h1>
    </header>
    @if ($stories->isEmpty())
        <p class="py-16 text-center text-[var(--muted)]">{{ __('Nothing published here yet.') }}</p>
    @else
        <div class="grid gap-4 py-10 sm:grid-cols-2 lg:grid-cols-3">@foreach ($stories as $s)<x-story :t="$s" variant="overlay" />@endforeach</div>
        {{ $stories->links() }}
    @endif
</section>
@endsection
