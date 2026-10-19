@extends('layouts.site')

@section('content')
<article class="wrap pt-8 md:pt-12">
    <div class="mx-auto max-w-3xl">
        <p class="kicker">Doogsan Media</p>
        <h1 class="headline headline-xl mt-1">{{ $page->title }}</h1>
        @if ($page->summary)<p class="dek mt-4 text-xl">{{ $page->summary }}</p>@endif
        <div class="story-body mt-8 [&>p:first-of-type]:first-letter:!text-[1em] [&>p:first-of-type]:first-letter:!float-none [&>p:first-of-type]:first-letter:!p-0">{!! $page->body_html !!}</div>
        <p class="meta mt-10">{{ __('Last updated') }} {{ news_date($page->updated_at, false) }}</p>
    </div>
</article>
@endsection
