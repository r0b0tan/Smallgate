@props(['mark' => true, 'wordmark' => true])

{{-- The portal's mark, optionally followed by the name. Both come from the
     administration's "Erscheinungsbild", with the built-in look as fallback. --}}
@php
    $branding = \App\Models\Branding::current();
    $parts = $branding->nameParts();
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    @if ($mark)
        <x-logo-mark class="h-9 w-auto text-brand" />
    @endif

    @if ($wordmark)
        <span class="text-lg uppercase tracking-[0.06em] text-ink">
            @if ($parts)
                <span class="font-bold">{{ $parts[0] }}</span><span class="font-normal text-ink-muted">{{ $parts[1] }}</span>
            @else
                <span class="font-bold">{{ $branding->displayName() }}</span>
            @endif
        </span>
    @endif
</span>
