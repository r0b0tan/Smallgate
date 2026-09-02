@extends('layouts.app')

@section('title', 'Profil')
@section('header', 'Profil')
@section('subheader', 'Ihre Zugangsdaten für das Kundenportal.')

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="sg-card">
            <h2 class="text-lg font-semibold text-white">Angaben</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <x-field name="name" label="Name" :value="$user->name" required autocomplete="name" />
                <x-field name="email" label="E-Mail-Adresse" type="email" :value="$user->email" required
                         autocomplete="username" />

                <div class="flex justify-end">
                    <button type="submit" class="sg-btn-primary">Speichern</button>
                </div>
            </form>

            <dl class="mt-6 space-y-2 border-t border-white/5 pt-4 text-sm">
                <div class="flex justify-between">
                    <dt class="sg-muted">Rolle</dt>
                    <dd class="text-white/70">{{ $user->role->label() }}</dd>
                </div>
                @if ($user->customer)
                    <div class="flex justify-between">
                        <dt class="sg-muted">Kunde</dt>
                        <dd class="text-white/70">{{ $user->customer->name }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="sg-muted">Letzte Anmeldung</dt>
                    <dd class="text-white/70">
                        {{ $user->last_login_at?->format('d.m.Y H:i') ?? '–' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="sg-card">
            <h2 class="text-lg font-semibold text-white">Passwort ändern</h2>
            <p class="mt-1 text-sm sg-faint">
                Nach der Änderung werden alle anderen Sitzungen abgemeldet.
            </p>

            <form method="POST" action="{{ route('profile.password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-field name="current_password" label="Aktuelles Passwort" type="password" required
                         autocomplete="current-password" />
                <x-field name="password" label="Neues Passwort" type="password" required
                         autocomplete="new-password" hint="Mindestens 12 Zeichen." />
                <x-field name="password_confirmation" label="Neues Passwort bestätigen" type="password" required
                         autocomplete="new-password" />

                <div class="flex justify-end">
                    <button type="submit" class="sg-btn-primary">Passwort ändern</button>
                </div>
            </form>
        </div>
    </div>
@endsection
