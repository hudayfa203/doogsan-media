@if ($paginator->hasPages())
    <nav class="mt-10 flex items-center justify-between gap-4 border-t border-[var(--rule)] pt-5 text-sm font-bold" aria-label="{{ __('Pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="text-[var(--muted)] opacity-50">← {{ __('Newer') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="hover:text-[var(--accent)]">← {{ __('Newer') }}</a>
        @endif
        <span class="text-[var(--muted)] font-semibold">{{ __('Page :p of :t', ['p' => $paginator->currentPage(), 't' => $paginator->lastPage()]) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="hover:text-[var(--accent)]">{{ __('Older') }} →</a>
        @else
            <span class="text-[var(--muted)] opacity-50">{{ __('Older') }} →</span>
        @endif
    </nav>
@endif
