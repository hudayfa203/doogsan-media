@if ($paginator->hasPages())
    <nav class="flex items-center justify-between text-xs">
        <span class="text-[var(--color-ink-400)]">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())<span class="btn btn-secondary btn-sm opacity-40">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm">Previous</a>@endif
            @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm">Next</a>@else<span class="btn btn-secondary btn-sm opacity-40">Next</span>@endif
        </div>
    </nav>
@endif
