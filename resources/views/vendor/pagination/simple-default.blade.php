@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" style="display: flex; justify-content: space-between; align-items: center;">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; background: var(--purple-200); color: var(--purple-400); cursor: not-allowed;">
                Previous
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; background: var(--purple-600); color: white; text-decoration: none; transition: all 0.3s;" onmouseover="this.style.background='var(--purple-700)'" onmouseout="this.style.background='var(--purple-600)'">
                Previous
            </a>
        @endif

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; background: var(--purple-600); color: white; text-decoration: none; transition: all 0.3s;" onmouseover="this.style.background='var(--purple-700)'" onmouseout="this.style.background='var(--purple-600)'">
                Next
            </a>
        @else
            <span style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; background: var(--purple-200); color: var(--purple-400); cursor: not-allowed;">
                Next
            </span>
        @endif
    </nav>
@endif
