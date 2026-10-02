@if (session('status'))
    <div class="mb-6 sg-alert-status" role="status">
        {{ session('status') }}
    </div>
@endif

@if (session('notice'))
    <div class="mb-6 sg-alert-notice" role="status">
        {{ session('notice') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-6 sg-alert-error" role="alert">
        {{ session('error') }}
    </div>
@endif
