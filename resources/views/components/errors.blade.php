@if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-500/10 px-4 py-3 ring-1 ring-inset ring-red-500/30" role="alert">
        <p class="text-sm font-medium text-red-300">Bitte prüfen Sie Ihre Eingaben.</p>
        <ul class="mt-1 list-inside list-disc text-xs text-red-300/80">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
