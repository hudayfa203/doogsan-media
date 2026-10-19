@props(['title', 'subtitle' => null, 'eyebrow' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1 class="text-2xl font-extrabold tracking-tight text-white">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-[var(--color-ink-400)]">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>
