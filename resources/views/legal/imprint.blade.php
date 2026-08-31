@extends('layouts.guest')

@section('title', 'Impressum')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Impressum</h1>

    {{-- Placeholder page. All values come from environment variables, so no
         personal data of the operator is stored in the repository. Replace the
         wording below with the reviewed legal text before going live. --}}
    <div class="mt-4 space-y-4 text-sm text-white/60">
        <section>
            <h2 class="font-medium text-white/80">Angaben gemäß § 5 DDG</h2>
            <p class="mt-1 whitespace-pre-line">{{ $legal['company'] }}
{{ $legal['address'] ?: '[Anschrift über LEGAL_ADDRESS konfigurieren]' }}</p>
        </section>

        @if ($legal['represented_by'])
            <section>
                <h2 class="font-medium text-white/80">Vertreten durch</h2>
                <p class="mt-1">{{ $legal['represented_by'] }}</p>
            </section>
        @endif

        <section>
            <h2 class="font-medium text-white/80">Kontakt</h2>
            <p class="mt-1">
                {{ $legal['email'] ?: '[E-Mail über LEGAL_EMAIL konfigurieren]' }}<br>
                {{ $legal['phone'] ?: '' }}
            </p>
        </section>

        @if ($legal['vat_id'])
            <section>
                <h2 class="font-medium text-white/80">Umsatzsteuer-ID</h2>
                <p class="mt-1">{{ $legal['vat_id'] }}</p>
            </section>
        @endif

        @if ($legal['register_entry'])
            <section>
                <h2 class="font-medium text-white/80">Registereintrag</h2>
                <p class="mt-1">{{ $legal['register_entry'] }}</p>
            </section>
        @endif

        <p class="border-t border-white/5 pt-4 text-xs text-white/30">
            Platzhalterseite. Der endgültige Text ist vor dem Produktivbetrieb rechtlich zu prüfen.
        </p>
    </div>

    <a href="{{ route('login') }}" class="sg-btn-secondary mt-6 w-full">Zur Anmeldung</a>
@endsection
