@php
    $seg = request()->segment(1);
    if (in_array($seg, array_keys(editions()), true)) { app()->setLocale($seg); }
@endphp
@extends('layouts.site')

@section('content')
<section class="wrap py-24 text-center">
    <p class="font-serif text-[6rem] font-semibold leading-none text-[var(--accent)]">404</p>
    <h1 class="headline headline-lg mt-4">{{ __('We could not find that page.') }}</h1>
    <p class="dek mt-3">{{ __('It may have moved, or the link may be wrong.') }}</p>
    <form action="{{ lroute('search') }}" class="mx-auto mt-8 flex max-w-md gap-2"><input name="q" class="field" placeholder="{{ __('Search Doogsan Media') }}" aria-label="{{ __('Search') }}"><button class="btn btn-primary">{{ __('Search') }}</button></form>
    <a href="{{ lroute('home') }}" class="mt-6 inline-block font-bold underline">{{ __('Back to the homepage') }}</a>
</section>
@endsection
