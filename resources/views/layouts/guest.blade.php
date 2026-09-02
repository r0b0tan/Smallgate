@extends('layouts.base')

@section('body')
    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-md">
            <a href="{{ route('home') }}" class="flex items-center justify-center gap-2">
                <span class="font-display text-2xl font-bold tracking-tight text-white">
                    {{ config('app.name') }}
                </span>
            </a>
            <p class="mt-2 text-center text-sm sg-muted">Kundenportal</p>

            <div class="mt-8 sg-card">
                @yield('card')
            </div>

            <div class="mt-6 flex justify-center gap-4 text-xs sg-faint">
                <a class="hover:text-white" href="{{ route('legal.imprint') }}">Impressum</a>
                <a class="hover:text-white" href="{{ route('legal.privacy') }}">Datenschutz</a>
            </div>
        </div>
    </div>
@endsection
