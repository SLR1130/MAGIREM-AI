@if ($paginator->hasPages())
    <div class="pagination-wrapper">
        <!-- Previous Button -->
        <div class="pagination-nav prev">
            @if ($paginator->onFirstPage())
                <span style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #d1d5db; border: 2px solid #e5e7eb; cursor: not-allowed;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #8b5cf6; text-decoration: none; font-weight: 600; transition: all 0.3s; border: 2px solid #ddd6fe;" onmouseover="this.style.background='#f3e8ff'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background=''; this.style.transform='';">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
            @endif
        </div>

        <!-- Page Numbers -->
        <div class="pagination-center">
            <ul class="pagination" style="display: flex; justify-content: center; gap: 0.25rem; list-style: none; padding: 0; margin: 0;">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <li style="display: inline-block;"><span style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #d1d5db; border: 2px solid #e5e7eb; cursor: not-allowed; font-weight: 600;">«</span></li>
                @else
                    <li style="display: inline-block;"><a href="{{ $paginator->previousPageUrl() }}" style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #8b5cf6; text-decoration: none; font-weight: 600; transition: all 0.3s; border: 2px solid #ddd6fe;" onmouseover="this.style.background='#f3e8ff'; this.style.borderColor='#8b5cf6';" onmouseout="this.style.background=''; this.style.borderColor='#ddd6fe';">«</a></li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <li style="display: inline-block;"><span style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; color: #d1d5db; font-weight: 600;">{{ $element }}</span></li>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li style="display: inline-block;"><span style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; border: 2px solid #8b5cf6; font-weight: 600;">{{ $page }}</span></li>
                            @else
                                <li style="display: inline-block;"><a href="{{ $url }}" style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #8b5cf6; text-decoration: none; font-weight: 600; transition: all 0.3s; border: 2px solid #ddd6fe;" onmouseover="this.style.background='#f3e8ff'; this.style.borderColor='#8b5cf6'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background=''; this.style.borderColor='#ddd6fe'; this.style.transform='';">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <li style="display: inline-block;"><a href="{{ $paginator->nextPageUrl() }}" style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #8b5cf6; text-decoration: none; font-weight: 600; transition: all 0.3s; border: 2px solid #ddd6fe;" onmouseover="this.style.background='#f3e8ff'; this.style.borderColor='#8b5cf6';" onmouseout="this.style.background=''; this.style.borderColor='#ddd6fe';">»</a></li>
                @else
                    <li style="display: inline-block;"><span style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #d1d5db; border: 2px solid #e5e7eb; cursor: not-allowed; font-weight: 600;">»</span></li>
                @endif
            </ul>
        </div>

        <!-- Next Button -->
        <div class="pagination-nav next">
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #8b5cf6; text-decoration: none; font-weight: 600; transition: all 0.3s; border: 2px solid #ddd6fe;" onmouseover="this.style.background='#f3e8ff'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background=''; this.style.transform='';">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            @else
                <span style="display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 8px; color: #d1d5db; border: 2px solid #e5e7eb; cursor: not-allowed;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
            @endif
        </div>
    </div>
@endif
