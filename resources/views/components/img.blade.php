@props(['media' => null, 'width' => 960, 'eager' => false, 'sizes' => '100vw', 'alt' => null])
@if ($media && $media->isImage())
    <img src="{{ $media->url($width) }}" @if ($media->srcset()) srcset="{{ $media->srcset() }}" sizes="{{ $sizes }}" @endif
         alt="{{ $alt ?? $media->altFor() }}" @if ($media->width) width="{{ $media->width }}" height="{{ $media->height }}" @endif
         loading="{{ $eager ? 'eager' : 'lazy' }}" @if ($eager) fetchpriority="high" @endif decoding="async" {{ $attributes }}>
@else
    <div {{ $attributes->class('flex h-full w-full items-center justify-center bg-[var(--color-forest-900)]') }} role="img" aria-label="Doogsan Media">
        <img src="{{ asset('images/brand/doogsan-mark-dark.png') }}" alt="" class="h-1/4 w-auto opacity-25">
    </div>
@endif
