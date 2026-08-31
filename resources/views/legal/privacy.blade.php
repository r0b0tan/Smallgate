@extends('layouts.guest')

@section('title', 'Datenschutzerklärung')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Datenschutzerklärung</h1>

    {{-- Placeholder page. The technical statements below describe what the
         application actually does, so they stay accurate; the legal wording
         still has to be reviewed before going live. --}}
    <div class="mt-4 space-y-4 text-sm text-white/60">
        <section>
            <h2 class="font-medium text-white/80">Verantwortlich</h2>
            <p class="mt-1 whitespace-pre-line">{{ $legal['company'] }}
{{ $legal['address'] ?: '[Anschrift über LEGAL_ADDRESS konfigurieren]' }}</p>
            <p class="mt-1">{{ $legal['email'] ?: '[E-Mail über LEGAL_EMAIL konfigurieren]' }}</p>
        </section>

        <section>
            <h2 class="font-medium text-white/80">Verarbeitete Daten</h2>
            <p class="mt-1">
                Für den Zugang zum Portal verarbeiten wir Name, E-Mail-Adresse, das gehashte Passwort
                sowie den Zeitpunkt der letzten Anmeldung. Diese Daten sind zur Bereitstellung des
                Zugangs erforderlich.
            </p>
        </section>

        <section>
            <h2 class="font-medium text-white/80">Cookies</h2>
            <p class="mt-1">
                Es werden ausschließlich technisch notwendige Cookies gesetzt: ein Sitzungscookie für
                die Anmeldung und ein Cookie zum Schutz vor Cross-Site-Request-Forgery. Es findet kein
                Tracking statt, es werden keine Analysedienste und keine externen JavaScript-Dienste
                eingebunden. Schriftarten werden lokal ausgeliefert.
            </p>
        </section>

        <section>
            <h2 class="font-medium text-white/80">Ihre Rechte</h2>
            <p class="mt-1">
                Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der
                Verarbeitung, Datenübertragbarkeit sowie ein Beschwerderecht bei einer
                Aufsichtsbehörde. Wenden Sie sich dafür an die oben genannte Adresse.
            </p>
        </section>

        <p class="border-t border-white/5 pt-4 text-xs text-white/30">
            Platzhalterseite. Der endgültige Text ist vor dem Produktivbetrieb rechtlich zu prüfen.
        </p>
    </div>

    <a href="{{ route('login') }}" class="sg-btn-secondary mt-6 w-full">Zur Anmeldung</a>
@endsection
