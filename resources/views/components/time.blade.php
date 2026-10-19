@props(['at' => null, 'relative' => true])
@if ($at)
    <time datetime="{{ $at->toIso8601String() }}" @if ($relative) data-rel @endif title="{{ news_date($at) }}" {{ $attributes }}>{{ $relative ? rel_time($at) : news_date($at) }}</time>
@endif
