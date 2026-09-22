@if ($paginator->hasPages())
    <nav class="pagination-wrapper" aria-label="Pagination">
        <div class="pagination-content">
            <p class="pagination-info small text-muted">
                {{ __('Showing') }}
                <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
                {{ __('to') }}
                <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                {{ __('of') }}
                <span class="fw-semibold">{{ $paginator->total() }}</span>
                {{ __('results') }}
            </p>

            <ul class="pagination">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true" aria-label="Previous">
                        <span class="page-link">Previous</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">Previous</a>
                    </li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">Next</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="Next">
                        <span class="page-link">Next</span>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@endif
