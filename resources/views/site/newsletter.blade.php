@extends('layouts.site')

@section('content')
<section class="wrap pt-10 md:pt-16">
    <div class="mx-auto max-w-2xl text-center">
        <img src="{{ asset('images/brand/doogsan-mark-light.png') }}" alt="" class="mx-auto h-14 dark:hidden">
        <h1 class="headline headline-xl mt-6">{{ __('The Doogsan Briefing') }}</h1>
        <p class="dek mt-4">{{ __('The essential stories from Puntland, Somalia and the world — free, every weekday morning, in your language.') }}</p>
        @include('site.partials.flash')
        <form method="post" action="{{ lroute('newsletter.subscribe') }}" class="mx-auto mt-8 flex max-w-md gap-2">
            @csrf
            <input type="hidden" name="source" value="newsletter-page">
            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
            <input type="email" name="email" required class="field" placeholder="{{ __('Your email address') }}" aria-label="{{ __('Email address') }}">
            <button class="btn btn-primary flex-none">{{ __('Subscribe') }}</button>
        </form>
        <p class="mt-3 text-xs text-[var(--muted)]">{{ __('We will send a confirmation email. You can unsubscribe at any time.') }}</p>
    </div>
</section>
@endsection
