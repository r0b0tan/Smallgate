@extends('layouts.guest')

@section('title', 'Anmelden')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Anmelden</h1>
    <p class="mt-1 text-sm text-white/50">Bitte melden Sie sich mit Ihren Zugangsdaten an.</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-accent/10 px-4 py-3 text-sm text-accent ring-1 ring-inset ring-accent/30">
            {{ session('status') }}
        </div>
    @endif

    {{-- One generic error for wrong password, unknown address and blocked
         account alike -- the form is not an account enumeration oracle. --}}
    @error('email')
        <div class="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-300 ring-1 ring-inset ring-red-500/30"
             role="alert">
            {{ $message }}
        </div>
    @enderror

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <x-field name="email" label="E-Mail-Adresse" type="email" required
                 autocomplete="username" />

        <x-field name="password" label="Passwort" type="password" required
                 autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-white/60">
                <input type="checkbox" name="remember" value="1"
                       class="rounded border-white/20 bg-white/5 text-accent focus:ring-accent">
                Angemeldet bleiben
            </label>

            <a href="{{ route('password.request') }}" class="text-sm text-accent hover:text-accent-soft">
                Passwort vergessen?
            </a>
        </div>

        <button type="submit" class="sg-btn-primary w-full">Anmelden</button>
    </form>

    <p class="mt-6 border-t border-white/5 pt-4 text-xs text-white/30">
        Zugänge werden ausschließlich von {{ config('app.name') }} eingerichtet.
        Eine Registrierung ist nicht vorgesehen.
    </p>
@endsection
