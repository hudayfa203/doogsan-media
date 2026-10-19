@props(['title', 'href' => null, 'more' => null])
<div {{ $attributes->class('section-head') }}>
    <h2>{{ $title }}</h2>
    @if ($href)
        <a href="{{ $href }}">{{ $more ?? __('See all') }} <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M7.3 4.3a1 1 0 0 1 1.4 0l5 5a1 1 0 0 1 0 1.4l-5 5a1 1 0 0 1-1.4-1.4L11.6 10 7.3 5.7a1 1 0 0 1 0-1.4Z"/></svg></a>
    @endif
</div>
