@extends('layouts.guest')

@section('title', 'Zugang einrichten')

@section('card')
    <h1 class="font-display text-xl font-bold text-white">Zugang einrichten</h1>
    <p class="mt-1 text-sm sg-faint">
        Sie richten den Zugang für
        <span class="text-white">{{ $invitation->email }}</span>
        @if ($invitation->customer)
            im Bereich <span class="text-white">{{ $invitation->customer->name }}</span>
        @endif
        ein.
    </p>

    <x-errors />

    <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="mt-6 space-y-4">
        @csrf

        {{-- The email, the customer and the role all come from the invitation
             itself and are not part of this form. --}}
        <x-field name="name" label="Ihr Name" :value="$invitation->name" required
                 autocomplete="name" />

        <x-field name="password" label="Passwort" type="password" required
                 autocomplete="new-password" hint="Mindestens 12 Zeichen." />

        <x-field name="password_confirmation" label="Passwort bestätigen" type="password" required
                 autocomplete="new-password" />

        <button type="submit" class="sg-btn-primary w-full">Zugang aktivieren</button>
    </form>
@endsection
