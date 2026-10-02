@props(['mark' => true, 'wordmark' => true])

{{-- The portal's mark: an "S" on a navy square, optionally followed by the
     name. Inline, nothing fetched. --}}
@php
    $name = (string) config('app.name');
    // The product name is set in two weights; any other name stays as it is.
    $split = strcasecmp($name, 'Smallgate') === 0;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    @if ($mark)
        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand text-lg font-extrabold
                     italic text-white" aria-hidden="true">S</span>
    @endif

    @if ($wordmark)
        <span class="text-lg uppercase tracking-[0.06em] text-ink">
            @if ($split)
                <span class="font-bold">Small</span><span class="font-normal text-ink-muted">gate</span>
            @else
                <span class="font-bold">{{ $name }}</span>
            @endif
        </span>
    @endif
</span>
