@extends('layouts.guest')

@section('title', 'Nicht gefunden')

@section('card')
    <h1 class="text-2xl font-semibold tracking-tight">Seite nicht gefunden</h1>

    {{-- Deliberately identical for "does not exist" and "not yours": the page
         must not confirm that a foreign resource exists. --}}
    <p class="mt-3 text-base sg-muted">
        Diese Seite existiert nicht oder ist für Ihren Zugang nicht verfügbar.
    </p>

    <a href="{{ route('home') }}" class="sg-btn-primary mt-6 min-h-12 w-full text-base">Zur Startseite</a>
@endsection
