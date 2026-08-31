@extends('layouts.guest')

@section('title', 'Passwort zurücksetzen')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Neues Passwort vergeben</h1>

    <x-errors />

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-field name="email" label="E-Mail-Adresse" type="email" :value="$email" required
                 autocomplete="username" />

        <x-field name="password" label="Neues Passwort" type="password" required
                 autocomplete="new-password" hint="Mindestens 12 Zeichen." />

        <x-field name="password_confirmation" label="Neues Passwort bestätigen" type="password" required
                 autocomplete="new-password" />

        <button type="submit" class="sg-btn-primary w-full">Passwort speichern</button>
    </form>
@endsection
