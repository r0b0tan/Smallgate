@extends('layouts.guest')

@section('title', 'Einladung ungültig')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Einladung nicht mehr gültig</h1>

    {{-- Deliberately one message for "unknown", "expired" and "already used":
         the page must not confirm that a token ever existed. --}}
    <p class="mt-2 text-sm sg-faint">
        Dieser Einladungslink ist abgelaufen, wurde bereits verwendet oder ist unbekannt.
        Bitte fordern Sie bei Ihrem Ansprechpartner eine neue Einladung an.
    </p>

    <a href="{{ route('login') }}" class="sg-btn-secondary mt-6 w-full">Zur Anmeldung</a>
@endsection
