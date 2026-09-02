@extends('layouts.guest')

@section('title', 'Passwort vergessen')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Passwort vergessen</h1>
    <p class="mt-1 text-sm sg-faint">
        Wir senden Ihnen einen Link, mit dem Sie ein neues Passwort vergeben können.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-accent/10 px-4 py-3 text-sm text-accent ring-1 ring-inset ring-accent/30"
             role="status">
            {{ session('status') }}
        </div>
    @endif

    <x-errors />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <x-field name="email" label="E-Mail-Adresse" type="email" required
                 autocomplete="username" />

        <button type="submit" class="sg-btn-primary w-full">Link anfordern</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="text-accent hover:text-accent-soft">Zurück zur Anmeldung</a>
    </p>
@endsection
