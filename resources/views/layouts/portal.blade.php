@extends('layouts.base')

{{-- The customer's layout. No navigation at all: there is one page to be on.
     Profile and sign-out sit behind the initials, out of the way. --}}

@section('theme', 'theme-light')

@php
    $user = auth()->user();
    $words = preg_split('/\s+/', trim((string) $user?->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
@endphp

@section('body')
    <div class="flex min-h-full flex-col">
        <header class="sticky top-0 z-20 border-b border-ink/5 bg-white/75 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('portal.dashboard') }}" aria-label="{{ config('app.name') }} – zur Übersicht">
                    <x-logo />
                </a>

                <div class="flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('profile.edit') }}"
                       class="flex min-h-11 items-center gap-3 rounded-full py-1 pl-1 pr-1 transition hover:bg-ink/5 sm:pr-4"
                       title="Ihre Zugangsdaten">
                        <span class="flex size-10 items-center justify-center rounded-full bg-brand text-sm font-bold text-white"
                              aria-hidden="true">{{ $initials }}</span>
                        <span class="hidden text-base font-medium sm:inline">{{ $user?->name }}</span>
                        <span class="sr-only">– Ihre Zugangsdaten</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex min-h-11 items-center gap-2 rounded-full px-3 text-base font-medium text-ink-muted
                                       transition hover:bg-ink/5 hover:text-ink">
                            <x-icon name="logout" />
                            <span>Abmelden</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-14">
            <x-flash />

            @hasSection('header')
                <div class="mb-8">
                    <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-2 text-base sg-link">
                        <x-icon name="arrow-left" /> Zurück zur Übersicht
                    </a>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">@yield('header')</h1>
                    @hasSection('subheader')
                        <p class="mt-2 text-lg sg-muted">@yield('subheader')</p>
                    @endif
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="mx-auto w-full max-w-6xl px-4 pb-10 sm:px-6">
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm sg-muted">
                <span>{{ config('app.name') }} · Einfach ansehen und Rückmeldung geben</span>
                <a class="hover:underline" href="{{ route('legal.imprint') }}">Impressum</a>
                <a class="hover:underline" href="{{ route('legal.privacy') }}">Datenschutz</a>
            </div>
        </footer>
    </div>
@endsection
