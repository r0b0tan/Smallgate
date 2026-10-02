@extends('layouts.guest')

@section('title', 'Passwort vergessen')

@section('card')
    <h1 class="text-2xl font-semibold tracking-tight">Passwort vergessen</h1>
    <p class="mt-2 text-base sg-muted">
        Wir senden Ihnen einen Link, mit dem Sie ein neues Passwort vergeben können.
    </p>

    <div class="mt-5"><x-errors /></div>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-field name="email" label="E-Mail-Adresse" type="email" required
                 autocomplete="username" />

        <button type="submit" class="sg-btn-primary min-h-12 w-full text-base">Link anfordern</button>
    </form>

    <p class="mt-6 text-center text-base">
        <a href="{{ route('login') }}" class="sg-link">Zurück zur Anmeldung</a>
    </p>
@endsection
