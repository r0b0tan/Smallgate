@props(['message'])

{{-- An empty state without a way out is a dead end, so the slot takes the
     action that would otherwise only sit in the page header. --}}
<div class="rounded-xl border border-dashed border-white/10 px-6 py-12 text-center">
    <p class="text-sm sg-muted">{{ $message }}</p>

    @if (! $slot->isEmpty())
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
