{{-- The portal's mark: a gate as a rounded square. Inline SVG, nothing fetched. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <span class="flex size-10 items-center justify-center rounded-xl bg-brand text-white" aria-hidden="true">
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="6" width="16" height="14" rx="2"/>
            <path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"/>
        </svg>
    </span>
    <span class="text-xl font-bold tracking-tight text-ink">{{ config('app.name') }}</span>
</span>
