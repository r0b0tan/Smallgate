{{-- Page navigation for every paginated list, in the portal's look. Laravel's
     bundled views follow the system dark mode, which the portal does not. --}}
@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-4" aria-label="Seiten">
        <p class="text-sm sg-muted">
            Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="sg-btn-secondary cursor-not-allowed opacity-50" aria-disabled="true">
                    <x-icon name="arrow-left" class="size-4" /> Zurück
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="sg-btn-secondary">
                    <x-icon name="arrow-left" class="size-4" /> Zurück
                </a>
            @endif

            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-2 text-sm sg-muted" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md bg-brand px-3
                                             text-sm font-semibold text-white" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md px-3
                                                          text-sm font-semibold text-ink hover:bg-white hover:ring-1 hover:ring-line"
                                   aria-label="Seite {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="sg-btn-secondary">
                    Weiter <x-icon name="arrow-left" class="size-4 rotate-180" />
                </a>
            @else
                <span class="sg-btn-secondary cursor-not-allowed opacity-50" aria-disabled="true">
                    Weiter <x-icon name="arrow-left" class="size-4 rotate-180" />
                </span>
            @endif
        </div>
    </nav>
@endif
