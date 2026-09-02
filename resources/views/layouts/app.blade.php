@extends('layouts.base')

@php
    $user = auth()->user();

    // Customers get no navigation at all: they have exactly one destination, and
    // a bar with a single item is decoration. The breadcrumb takes them back.
    $navigation = $user?->isAdmin()
        ? [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
            ['label' => 'Kunden', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
            ['label' => 'Projekte', 'route' => 'admin.projects.index', 'active' => 'admin.projects.*'],
        ]
        : [];
@endphp

@section('body')
    <div class="min-h-full">
        <header class="border-b border-white/5 bg-surface/60">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <a href="{{ route('home') }}" class="font-display text-lg font-bold text-white">
                        {{ config('app.name') }}
                    </a>

                    <div class="hidden gap-1 md:flex">
                        @foreach ($navigation as $item)
                            <a href="{{ route($item['route']) }}"
                               @if (request()->routeIs($item['active'])) aria-current="page" @endif
                               @class([
                                   'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                   'bg-white/10 text-white' => request()->routeIs($item['active']),
                                   'sg-muted hover:bg-white/5 hover:text-white' => ! request()->routeIs($item['active']),
                               ])>{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('profile.edit') }}" class="text-sm sg-muted hover:text-white">
                        {{ $user?->name }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sg-btn-secondary">Abmelden</button>
                    </form>

                    {{-- Nothing to fold away when there is no navigation. --}}
                    @if ($navigation !== [])
                        <button type="button" data-toggle="mobile-nav" aria-expanded="false"
                                aria-controls="mobile-nav"
                                class="sg-btn-secondary md:hidden">
                            <span class="sr-only">Menü</span>
                            &#9776;
                        </button>
                    @endif
                </div>
            </nav>

            @if ($navigation !== [])
                <div id="mobile-nav" hidden class="border-t border-white/5 px-4 py-2 md:hidden">
                    @foreach ($navigation as $item)
                        <a href="{{ route($item['route']) }}"
                           @if (request()->routeIs($item['active'])) aria-current="page" @endif
                           class="block rounded-lg px-3 py-2 text-sm text-white/70 hover:bg-white/5 hover:text-white">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-flash />

            @hasSection('header')
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        @hasSection('breadcrumb')
                            <div class="mb-1 text-xs sg-faint">@yield('breadcrumb')</div>
                        @endif
                        <h1 class="font-display text-2xl font-bold text-white">@yield('header')</h1>
                        @hasSection('subheader')
                            <p class="mt-1 text-sm sg-faint">@yield('subheader')</p>
                        @endif
                    </div>
                    @hasSection('actions')
                        <div class="flex flex-wrap gap-2">@yield('actions')</div>
                    @endif
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-between gap-4 border-t border-white/5 pt-6 text-xs sg-faint">
                <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
                <span class="flex gap-4">
                    <a class="hover:text-white" href="{{ route('legal.imprint') }}">Impressum</a>
                    <a class="hover:text-white" href="{{ route('legal.privacy') }}">Datenschutz</a>
                </span>
            </div>
        </footer>
    </div>
@endsection
