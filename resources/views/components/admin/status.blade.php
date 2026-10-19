@props(['status'])
<span {{ $attributes->class(['pill', 'pill-'.$status]) }}>{{ __('status.'.$status) }}</span>
