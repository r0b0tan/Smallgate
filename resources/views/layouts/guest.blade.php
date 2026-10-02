@extends('layouts.base')

{{-- Sign-in, invitation and password pages are the first thing a customer
     sees, so they share the portal's calm light theme. --}}
@section('theme', 'theme-light')

@section('body')
    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-md">
            <a href="{{ route('home') }}" class="flex justify-center">
                <x-logo />
            </a>
            <p class="mt-3 text-center text-base sg-muted">Ihr persönlicher Kundenbereich</p>

            <div class="mt-8 sg-card p-6 sm:p-8">
                @yield('card')
            </div>

            <div class="mt-6 flex justify-center gap-6 text-sm sg-muted">
                <a class="hover:underline" href="{{ route('legal.imprint') }}">Impressum</a>
                <a class="hover:underline" href="{{ route('legal.privacy') }}">Datenschutz</a>
            </div>
        </div>
    </div>
@endsection
