@extends('layouts.guest')

@section('title', 'Nicht gefunden')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Nicht gefunden</h1>

    {{-- Deliberately identical for "does not exist" and "not yours": the page
         must not confirm that a foreign resource exists. --}}
    <p class="mt-2 text-sm sg-faint">
        Diese Seite existiert nicht oder ist für Ihren Zugang nicht verfügbar.
    </p>

    <a href="{{ route('home') }}" class="sg-btn-secondary mt-6 w-full">Zur Startseite</a>
@endsection
