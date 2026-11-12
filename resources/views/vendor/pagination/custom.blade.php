@if ($paginator->hasPages())
    <nav class="pagination-wrapper" role="navigation" aria-label="Saved entries pagination">
        <div class="pagination-nav prev">
            @if ($paginator->onFirstPage())
                <span class="pagination-control is-disabled" aria-disabled="true" aria-label="No previous page">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </span>
            @else
                <a class="pagination-control" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Go to previous page">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
            @endif
        </div>

        <div class="pagination-center">
            <ul class="pagination" aria-label="Page numbers">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="pagination-ellipsis" aria-hidden="true">{{ $element }}</li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="active"><span aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span></li>
                            @else
                                <li><a href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </ul>
        </div>

        <div class="pagination-nav next">
            @if ($paginator->hasMorePages())
                <a class="pagination-control" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Go to next page">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            @else
                <span class="pagination-control is-disabled" aria-disabled="true" aria-label="No next page">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
