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
                    <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span class="page-link">{{ __('pagination.previous') }}</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">{{ __('pagination.previous') }}</a>
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
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">{{ __('pagination.next') }}</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span class="page-link">{{ __('pagination.next') }}</span>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@endif
