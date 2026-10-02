@extends('layouts.base')

{{-- Sign-in, invitation, password and legal pages: the first thing a customer
     sees, in the same look as the portal behind it. --}}

@section('body')
    <div class="flex min-h-full flex-col">
        <header class="border-b border-line bg-white/80">
            <div class="mx-auto flex h-16 max-w-6xl items-center px-4 sm:px-6">
                <a href="{{ route('home') }}" aria-label="{{ config('app.name') }} – zur Startseite">
                    <x-logo />
                </a>
            </div>
        </header>

        <main class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6">
            <div class="mx-auto w-full max-w-md">
                <p class="sg-eyebrow text-center">Ihr persönlicher Kundenbereich</p>

                <div class="mt-4 sg-card p-6 sm:p-8">
                    @yield('card')
                </div>
            </div>
        </main>

        <footer class="px-4 pb-8">
            <div class="flex justify-center gap-6 text-sm sg-muted">
                <a class="hover:text-ink hover:underline" href="{{ route('legal.imprint') }}">Impressum</a>
                <a class="hover:text-ink hover:underline" href="{{ route('legal.privacy') }}">Datenschutz</a>
            </div>
        </footer>

        <x-flash class="bottom-6" />
    </div>
@endsection
