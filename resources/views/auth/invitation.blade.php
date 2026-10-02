@extends('layouts.guest')

@section('title', 'Zugang einrichten')

@section('card')
    <h1 class="text-2xl font-semibold tracking-tight">Zugang einrichten</h1>
    <p class="mt-2 text-base sg-muted">
        Sie richten den Zugang für
        <strong class="text-ink">{{ $invitation->email }}</strong>
        @if ($invitation->customer)
            ({{ $invitation->customer->name }})
        @endif
        ein. Wählen Sie dafür einmalig ein Passwort – danach sehen Sie direkt Ihre Entwürfe.
    </p>

    <div class="mt-5"><x-errors /></div>

    <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="space-y-5">
        @csrf

        {{-- The email, the customer and the role all come from the invitation
             itself and are not part of this form. --}}
        <x-field name="name" label="Ihr Name" :value="$invitation->name" required
                 autocomplete="name" />

        <x-field name="password" label="Passwort" type="password" required
                 autocomplete="new-password" hint="Mindestens 12 Zeichen. Ein kurzer Satz lässt sich gut merken." />

        <x-field name="password_confirmation" label="Passwort wiederholen" type="password" required
                 autocomplete="new-password" />

        <button type="submit" class="sg-btn-primary min-h-12 w-full text-base">Zugang aktivieren</button>
    </form>
@endsection
