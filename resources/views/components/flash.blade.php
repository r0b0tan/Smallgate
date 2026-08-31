@if (session('status'))
    <div class="mb-6 rounded-lg bg-accent/10 px-4 py-3 text-sm text-accent ring-1 ring-inset ring-accent/30"
         role="status">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-6 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-300 ring-1 ring-inset ring-red-500/30"
         role="alert">
        {{ session('error') }}
    </div>
@endif
