@extends('layouts.guest')

@section('title', 'Einladung ungültig')

@section('card')
    <h1 class="text-2xl font-bold">Einladung nicht mehr gültig</h1>

    {{-- Deliberately one message for "unknown", "expired" and "already used":
         the page must not confirm that a token ever existed. --}}
    <p class="mt-3 text-base sg-muted">
        Der Einladungslink ist abgelaufen, wurde bereits verwendet oder ist unbekannt.
        Antworten Sie einfach auf unsere E-Mail – wir schicken Ihnen gern einen neuen Link.
    </p>

    <p class="mt-3 text-base sg-muted">
        Haben Sie Ihren Zugang schon eingerichtet? Dann melden Sie sich einfach an.
    </p>

    <a href="{{ route('login') }}" class="sg-btn-primary mt-6 w-full">Zur Anmeldung</a>
@endsection
