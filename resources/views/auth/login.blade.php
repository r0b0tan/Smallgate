@extends('layouts.guest')

@section('title', 'Anmelden')

@section('card')
    <h1 class="text-2xl font-semibold tracking-tight">Willkommen</h1>
    <p class="mt-2 text-base sg-muted">
        Bitte melden Sie sich mit Ihrer E-Mail-Adresse und Ihrem Passwort an.
    </p>

    @if (session('status'))
        <div class="mt-5 sg-alert-status" role="status">
            {{ session('status') }}
        </div>
    @endif

    {{-- One generic error for wrong password, unknown address and blocked
         account alike -- the form is not an account enumeration oracle. --}}
    @error('email')
        <div class="mt-5 sg-alert-error" role="alert">
            {{ $message }}
        </div>
    @enderror

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <x-field name="email" label="E-Mail-Adresse" type="email" required
                 autocomplete="username" />

        <x-field name="password" label="Passwort" type="password" required
                 autocomplete="current-password" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-3 text-base sg-muted">
                <input type="checkbox" name="remember" value="1"
                       class="size-5 rounded border-line accent-brand">
                Angemeldet bleiben
            </label>

            <a href="{{ route('password.request') }}" class="text-base sg-link">
                Passwort vergessen?
            </a>
        </div>

        <button type="submit" class="sg-btn-primary min-h-12 w-full text-base">Anmelden</button>
    </form>

    <p class="mt-6 border-t border-line pt-5 text-sm sg-muted">
        Fragen? Antworten Sie einfach auf unsere E-Mail.
        Zugänge werden ausschließlich von uns eingerichtet. Eine Registrierung ist nicht vorgesehen.
    </p>
@endsection
