@extends('layouts.base')

@section('title', 'Anmelden')

{{-- The sign-in page has its own split card instead of the guest layout: the
     brand panel on the left carries the logo, the form sits on the right. --}}
@php
    $contactEmail = (string) config('smallgate.contact_email');
    $branding = \App\Models\Branding::current();
    $parts = $branding->nameParts();
@endphp

@section('body')
    <div class="sg-login-ground flex min-h-full flex-col items-center justify-center px-4 py-10 sm:px-6">
        <main class="w-full max-w-3xl">
            <div class="sg-login-card md:grid md:grid-cols-[39fr_61fr]">
                <div class="sg-login-panel relative overflow-hidden px-6 py-8 text-white sm:px-14 sm:py-10 md:pt-14 md:pb-48">
                    <x-logo-mark on="dark" class="h-14 w-auto text-sky" />

                    <p class="mt-4 text-[2.125rem] leading-none uppercase tracking-[0.01em] break-words">
                        @if ($parts)
                            <span class="font-semibold text-white">{{ $parts[0] }}</span><span class="font-semibold text-slate-accent">{{ $parts[1] }}</span>
                        @else
                            <span class="font-semibold text-white">{{ $branding->displayName() }}</span>
                        @endif
                    </p>

                    <p class="mt-6 text-[1.3125rem] leading-snug text-slate-text">
                        Entwürfe ansehen.<br>
                        Rückmeldung geben.
                    </p>

                    {{-- The watermark is the built-in "S"; it stays away from a customer's own logo. --}}
                    @unless ($branding->hasAnyLogo())
                        <x-brand-watermark class="pointer-events-none absolute bottom-0 left-0 hidden w-full md:block" />
                    @endunless
                </div>

                <div class="px-6 py-10 sm:px-16 sm:pt-14 sm:pb-11">
                    <h1 class="text-[2.375rem] leading-tight font-semibold tracking-tight">Anmelden</h1>
                    <p class="mt-2 text-[1.0625rem] sg-muted">
                        Nutzen Sie Ihre Zugangsdaten aus der <span class="whitespace-nowrap">Einladungs-E-Mail.</span>
                    </p>

                    {{-- One generic error for wrong password, unknown address and blocked
                         account alike -- the form is not an account enumeration oracle. --}}
                    @error('email')
                        <div class="mt-6 sg-alert-error" role="alert">
                            {{ $message }}
                        </div>
                    @enderror

                    <form method="POST" action="{{ route('login') }}" class="mt-8">
                        @csrf

                        <label for="email" class="sg-login-label">E-Mail-Adresse</label>
                        <div class="relative mt-2">
                            <x-icon name="mail" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-field-icon" />
                            <input id="email" name="email" type="email" value="{{ old('email') }}"
                                   required autocomplete="username"
                                   placeholder="Ihre E-Mail-Adresse"
                                   class="sg-login-field pr-3.5">
                        </div>

                        <label for="password" class="mt-6 sg-login-label">Passwort</label>
                        <div class="relative mt-2">
                            <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-field-icon" />
                            <input id="password" name="password" type="password"
                                   required autocomplete="current-password"
                                   placeholder="Ihr Passwort"
                                   class="sg-login-field pr-12">

                            {{-- Shown by app.js only; without JavaScript the field
                                 simply stays masked. --}}
                            <button type="button" hidden data-password-toggle="password"
                                    aria-controls="password" aria-pressed="false" aria-label="Passwort anzeigen"
                                    class="absolute top-1/2 right-2 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-slate hover:bg-brand-tint">
                                <x-icon name="eye" data-show />
                                <x-icon name="eye-off" data-hide class="hidden" />
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-sm sg-error">{{ $message }}</p>
                        @enderror

                        <div class="mt-3 text-right">
                            <a href="{{ route('password.request') }}"
                               class="text-[0.9375rem] font-semibold text-link underline underline-offset-4 hover:text-brand-dark">
                                Passwort vergessen?
                            </a>
                        </div>

                        <button type="submit" class="mt-6 sg-btn-primary min-h-12 w-full rounded-lg text-[1.1875rem] font-medium">
                            <x-icon name="login" />
                            Anmelden
                        </button>
                    </form>

                    {{-- There is no self-registration: accounts only come by invitation. --}}
                    @if ($contactEmail !== '')
                        <p class="mt-7 border-t border-line-soft pt-5 text-center text-[0.9375rem] sg-muted">
                            Probleme beim Zugang?
                            <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Probleme beim Zugang') }}"
                               class="font-semibold text-link hover:underline underline-offset-4">Kontakt aufnehmen</a>
                        </p>
                    @endif
                </div>
            </div>

            <footer class="mt-6 flex justify-center gap-6 text-sm sg-muted">
                <a class="hover:text-ink hover:underline" href="{{ route('legal.imprint') }}">Impressum</a>
                <a class="hover:text-ink hover:underline" href="{{ route('legal.privacy') }}">Datenschutz</a>
            </footer>
        </main>

        <x-flash class="bottom-6" />
    </div>
@endsection
