{{-- Flash messages as toasts at the bottom right. CSS only: each one slides
     in, stays a few seconds (paused while hovered) and fades out -- no
     JavaScript. Errors stay longest. Form validation errors are not toasts;
     they stay next to the form (x-errors). --}}
@php
    $types = [
        'status' => ['icon' => 'check-circle', 'tone' => 'text-emerald-600', 'stay' => '[--toast-stay:5s]', 'role' => 'status'],
        'notice' => ['icon' => 'info-circle', 'tone' => 'text-amber-600', 'stay' => '[--toast-stay:8s]', 'role' => 'status'],
        'error' => ['icon' => 'alert-circle', 'tone' => 'text-red-600', 'stay' => '[--toast-stay:10s]', 'role' => 'alert'],
    ];

    // Written out so Tailwind finds the classes; inline styles are blocked by the CSP.
    $delays = ['[--toast-delay:0ms]', '[--toast-delay:120ms]', '[--toast-delay:240ms]', '[--toast-delay:360ms]'];

    $toasts = collect($types)
        ->filter(fn ($type, $key) => filled(session($key)))
        ->map(fn ($type, $key) => $type + ['message' => session($key)])
        ->take(4)
        ->values();
@endphp

@if ($toasts->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'pointer-events-none fixed right-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col sm:right-6']) }}>
        @foreach ($toasts as $toast)
            <div class="sg-toast {{ $toast['stay'] }} {{ $delays[$loop->index] }}" role="{{ $toast['role'] }}">
                <x-icon :name="$toast['icon']" class="mt-px size-5 shrink-0 {{ $toast['tone'] }}" />
                <p>{{ $toast['message'] }}</p>
            </div>
        @endforeach
    </div>
@endif
