@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="crm-pagination">
        <div class="flex flex-wrap items-center justify-center gap-1">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true"><span><i class="bi bi-chevron-left"></i></span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="bi bi-chevron-left"></i></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true"><span>{{ $element }}</span></span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"><span>{{ $page }}</span></span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"><i class="bi bi-chevron-right"></i></a>
            @else
                <span aria-disabled="true"><span><i class="bi bi-chevron-right"></i></span></span>
            @endif
        </div>
    </nav>
@endif
