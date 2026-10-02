@if ($errors->any())
    <div class="mb-4 sg-alert-error" role="alert">
        <p class="font-medium">Bitte prüfen Sie Ihre Eingaben.</p>
        <ul class="mt-1 list-inside list-disc opacity-90">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
