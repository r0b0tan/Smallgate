@extends('layouts.app')

@section('title', 'Zugänge')
@section('breadcrumb')
    <a href="{{ route('admin.customers.index') }}" class="hover:text-ink">Kunden</a> /
    <a href="{{ route('admin.customers.show', $customer) }}" class="hover:text-ink">{{ $customer->name }}</a>
@endsection
@section('header', 'Zugänge verwalten')
@section('subheader', 'Zugänge entstehen ausschließlich über eine Einladung – es gibt keine Registrierung.')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="sg-card lg:col-span-1">
            <h2 class="text-lg font-semibold">Benutzer einladen</h2>

            @if (! $customer->is_active)
                <p class="mt-4 rounded-md bg-brand-tint px-4 py-3 ring-1 ring-inset ring-line text-sm sg-faint">
                    Der Kunde ist deaktiviert. Es können keine Einladungen versendet werden.
                </p>
            @else
                <x-errors />

                <form method="POST" action="{{ route('admin.customers.invitations.store', $customer) }}"
                      class="mt-4 space-y-4">
                    @csrf

                    <x-field name="name" label="Name" required />
                    <x-field name="email" label="E-Mail-Adresse" type="email" required />

                    <p class="text-xs sg-faint">
                        Die eingeladene Person vergibt ihr Passwort selbst. Der Link ist
                        {{ config('smallgate.invitations.ttl_hours') }} Stunden gültig und nur einmal verwendbar.
                    </p>

                    <button type="submit" class="sg-btn-primary w-full">Einladung senden</button>
                </form>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="sg-card">
                <h2 class="text-lg font-semibold">Bestehende Zugänge</h2>

                @if ($users->isEmpty())
                    <div class="mt-4"><x-empty message="Noch keine aktiven Zugänge." /></div>
                @else
                    <ul class="mt-4 divide-y divide-line">
                        @foreach ($users as $user)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                                    <p class="truncate text-xs sg-faint">{{ $user->email }}</p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="sg-badge {{ $user->is_active
                                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                        : 'bg-red-50 text-red-800 ring-red-200' }}">
                                        {{ $user->is_active ? 'Aktiv' : 'Gesperrt' }}
                                    </span>

                                    <form method="POST"
                                          action="{{ route('admin.customers.users.block', [$customer, $user]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="{{ $user->is_active ? 'sg-btn-danger' : 'sg-btn-secondary' }}">
                                            {{ $user->is_active ? 'Sperren' : 'Entsperren' }}
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="sg-card">
                <h2 class="text-lg font-semibold">Einladungen</h2>

                @if ($invitations->isEmpty())
                    <div class="mt-4"><x-empty message="Bisher wurden keine Einladungen versendet." /></div>
                @else
                    <ul class="mt-4 divide-y divide-line">
                        @foreach ($invitations as $invitation)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm">{{ $invitation->email }}</p>
                                    <p class="truncate text-xs sg-faint">
                                        {{ $invitation->statusLabel() }} ·
                                        gültig bis {{ $invitation->expires_at->format('d.m.Y H:i') }}
                                    </p>
                                </div>

                                @unless ($invitation->isAccepted())
                                    <div class="flex gap-2">
                                        <form method="POST"
                                              action="{{ route('admin.customers.invitations.resend', [$customer, $invitation]) }}">
                                            @csrf
                                            <button type="submit" class="sg-btn-secondary">Erneut senden</button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('admin.customers.invitations.revoke', [$customer, $invitation]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sg-btn-danger">Zurückziehen</button>
                                        </form>
                                    </div>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
