@extends('layouts.guest')

@section('title', 'Passwort zurücksetzen')

@section('card')
    <h1 class="text-2xl font-bold">Neues Passwort vergeben</h1>

    <div class="mt-5"><x-errors /></div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-field name="email" label="E-Mail-Adresse" type="email" :value="$email" required
                 autocomplete="username" />

        <x-field name="password" label="Neues Passwort" type="password" required
                 autocomplete="new-password" hint="Mindestens 12 Zeichen." />

        <x-field name="password_confirmation" label="Neues Passwort wiederholen" type="password" required
                 autocomplete="new-password" />

        <button type="submit" class="sg-btn-primary w-full">Passwort speichern</button>
    </form>
@endsection
