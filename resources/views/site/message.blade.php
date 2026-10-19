@extends('layouts.site')

@section('content')
<section class="wrap py-20 text-center">
    <h1 class="headline headline-lg">{{ $title }}</h1>
    <p class="dek mt-3">{{ $message }}</p>
    <a href="{{ lroute('home') }}" class="btn btn-primary mt-8">{{ __('Back to the homepage') }}</a>
</section>
@endsection
