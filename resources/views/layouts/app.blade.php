@extends('layouts.base')

{{-- The signed-in shell for both roles: an icon rail on the left (a bar at the
     bottom on phones), the account at the top right, the page in between. --}}

@php
    $user = auth()->user();
    $branding = \App\Models\Branding::current();

    $words = preg_split('/\s+/', trim((string) $user?->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));

    // Customers see the company they sign in for, administrators themselves.
    $accountLabel = $user?->isAdmin() ? $user->name : ($user?->customer?->name ?? $user?->name);

    // Set by the nudge on the rail (app.js), so the next page starts expanded.
    $railOpen = request()->cookie('sg_rail') === 'open';

    $navigation = $user?->isAdmin()
        ? [
            ['label' => 'Dashboard', 'icon' => 'home', 'url' => route('admin.dashboard'), 'active' => 'admin.dashboard'],
            ['label' => 'Kunden', 'icon' => 'users', 'url' => route('admin.customers.index'), 'active' => 'admin.customers.*'],
            ['label' => 'Projekte', 'icon' => 'list', 'url' => route('admin.projects.index'), 'active' => 'admin.projects.*'],
            ['label' => 'Protokoll', 'icon' => 'clock', 'url' => route('admin.activities.index'), 'active' => 'admin.activities.*'],
            ['label' => 'Erscheinungsbild', 'icon' => 'palette', 'url' => route('admin.branding.edit'), 'active' => 'admin.branding.*'],
            ['label' => 'Ihre Zugangsdaten', 'icon' => 'user', 'url' => route('profile.edit'), 'active' => 'profile.*'],
        ]
        : [
            ['label' => 'Übersicht', 'icon' => 'home', 'url' => route('portal.dashboard'), 'active' => ['portal.dashboard', 'portal.projects.*', 'portal.previews.*']],
            // An archive of the answers given, not a chat: replies come by mail.
            ['label' => 'Nachrichten', 'icon' => 'chat', 'url' => route('portal.feedback.index'), 'active' => 'portal.feedback.index'],
            ['label' => 'Ihre Zugangsdaten', 'icon' => 'user', 'url' => route('profile.edit'), 'active' => 'profile.*'],
        ];
@endphp

@section('body')
    {{-- From md up the shell is exactly one screen high and only the page
         between top bar and footer bar scrolls; on phones the whole document
         scrolls as usual. --}}
    <div class="min-h-full transition-[padding] duration-200 ease-out motion-reduce:transition-none md:flex md:h-dvh md:flex-col md:pl-16 md:rail-open:pl-56" data-rail="{{ $railOpen ? 'open' : 'closed' }}">
        {{-- Icon rail. Each label sits next to its icon on hover and focus, and
             is always there for screen readers. Expanded, the labels are
             written out next to the icons. --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-16 flex-col bg-rail px-2.75 py-4 transition-[width] duration-200
                      ease-out motion-reduce:transition-none md:flex rail-open:w-56">
            <a href="{{ route('home') }}" class="flex size-10.5 items-center justify-center"
               aria-label="{{ $branding->displayName() }} – zur Startseite">
                <x-logo :wordmark="false" />
            </a>

            <nav class="mt-8 flex flex-col gap-3" aria-label="Hauptnavigation">
                @foreach ($navigation as $item)
                    @php($current = request()->routeIs(...(array) $item['active']))
                    <a href="{{ $item['url'] }}"
                       @if ($current) aria-current="page" @endif
                       @class([
                           'group relative flex h-10.5 items-center gap-3 rounded-md px-2.25 transition-colors',
                           'bg-brand text-white shadow-sm' => $current,
                           'text-ink-muted hover:bg-white hover:text-ink' => ! $current,
                       ])>
                        <x-icon :name="$item['icon']" class="size-6" />
                        <span class="sg-rail-label">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="mt-auto">
                @csrf
                <button type="submit"
                        class="group relative flex h-10.5 w-full items-center gap-3 rounded-md bg-brand px-2.25 text-white
                               transition-colors hover:bg-brand-dark">
                    <x-icon name="logout" class="size-6" />
                    <span class="sg-rail-label">Abmelden</span>
                </button>
            </form>

            {{-- A small round handle on the rail's edge, a mid grey that stands
                 out against rail and page alike. Its tooltip is also its
                 accessible name: only the phrase for the current state is
                 displayed. It needs app.js and stays hidden without it; the
                 labels then still show on hover. --}}
            <button type="button" hidden data-rail-toggle aria-expanded="{{ $railOpen ? 'true' : 'false' }}"
                    class="group absolute left-full top-1/2 flex size-7 -translate-x-1/2 -translate-y-1/2 items-center
                           justify-center rounded-full bg-handle text-white shadow-card transition-colors hover:bg-quiet">
                <x-icon name="chevron-right" class="size-4 stroke-[2.2] transition-transform duration-200 rail-open:rotate-180" />
                <span class="pointer-events-none absolute left-full z-40 ml-3 whitespace-nowrap rounded-md bg-ink px-2.5 py-1.5
                             text-sm font-medium text-white opacity-0 shadow-lift group-hover:opacity-100
                             group-hover:transition-opacity group-focus-visible:opacity-100">
                    <span class="rail-open:hidden">Menü öffnen</span>
                    <span class="hidden rail-open:inline">Menü einklappen</span>
                </span>
            </button>
        </aside>

        <header class="sticky top-0 z-20 shrink-0 border-b border-line bg-paper/90 backdrop-blur-md">
            <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                <a href="{{ route('home') }}" aria-label="{{ $branding->displayName() }} – zur Startseite">
                    {{-- The rail carries the mark on wider screens. --}}
                    <span class="md:hidden"><x-logo /></span>
                    <span class="hidden md:inline"><x-logo :mark="false" /></span>
                </a>

                <details class="relative" data-menu>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-full py-1 pl-3 pr-2
                                    transition-colors hover:bg-white">
                        <span class="hidden max-w-64 truncate text-sm font-medium sm:inline">{{ $accountLabel }}</span>
                        <span class="flex size-9 items-center justify-center rounded-full bg-accent text-sm font-bold text-white"
                              aria-hidden="true">{{ $initials }}</span>
                        <x-icon name="chevron-down" class="size-4 text-ink-muted" />
                        <span class="sr-only">Konto-Menü</span>
                    </summary>

                    <div class="absolute right-0 z-30 mt-2 w-72 overflow-hidden rounded-lg bg-white shadow-lift ring-1 ring-line">
                        <div class="border-b border-line px-4 py-3">
                            <p class="truncate text-sm font-semibold">{{ $user?->name }}</p>
                            <p class="truncate text-sm sg-muted">{{ $user?->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}"
                           class="flex items-center gap-3 px-4 py-3 text-sm font-medium hover:bg-brand-tint">
                            <x-icon name="user" class="text-ink-muted" /> Ihre Zugangsdaten
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-medium hover:bg-brand-tint">
                                <x-icon name="logout" class="text-ink-muted" /> Abmelden
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        {{-- Positioned, so absolutely placed content (sr-only labels among it)
             stays inside the scrolling area instead of stretching the window. --}}
        <div class="md:relative md:min-h-0 md:flex-1 md:overflow-y-auto">
            <main class="max-w-7xl px-4 pb-6 pt-6 sm:px-6 md:pb-10 lg:px-10 lg:pt-8">
                @hasSection('header')
                    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div class="min-w-0">
                            @hasSection('breadcrumb')
                                <div class="mb-1 text-sm sg-muted">@yield('breadcrumb')</div>
                            @endif
                            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">@yield('header')</h1>
                            @hasSection('subheader')
                                <p class="mt-1 text-sm sg-muted">@yield('subheader')</p>
                            @endif
                        </div>
                        @hasSection('actions')
                            <div class="flex flex-wrap gap-2">@yield('actions')</div>
                        @endif
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

        {{-- On wider screens a slim bar at the bottom edge, outside the scrolling
             page; on phones it ends the page, above the bottom bar. --}}
        <footer class="mx-4 mt-10 flex flex-wrap justify-between gap-4 border-t border-line pb-28 pt-6 text-sm sg-muted sm:mx-6
                       md:mx-0 md:mt-0 md:h-7 md:shrink-0 md:flex-nowrap md:items-center md:bg-paper md:px-6 md:py-0
                       md:text-xs lg:px-10">
            <span>&copy; {{ date('Y') }} {{ $branding->copyrightHolder() }}</span>
            <span class="flex gap-6">
                <a class="hover:text-ink hover:underline" href="{{ route('legal.imprint') }}">Impressum</a>
                <a class="hover:text-ink hover:underline" href="{{ route('legal.privacy') }}">Datenschutz</a>
            </span>
        </footer>

        {{-- The rail as a bottom bar on phones, labels written out. --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 backdrop-blur-md md:hidden"
             aria-label="Hauptnavigation">
            <div class="flex justify-around px-2 pb-[env(safe-area-inset-bottom)]">
                @foreach ($navigation as $item)
                    @php($current = request()->routeIs(...(array) $item['active']))
                    <a href="{{ $item['url'] }}"
                       @if ($current) aria-current="page" @endif
                       @class([
                           'flex min-w-0 flex-1 flex-col items-center gap-1 py-2.5 text-xs font-medium',
                           'text-brand' => $current,
                           'text-ink-muted' => ! $current,
                       ])>
                        <span @class(['flex h-8 w-12 items-center justify-center rounded-full', 'bg-brand-soft' => $current])>
                            <x-icon :name="$item['icon']" class="size-6" />
                        </span>
                        <span class="max-w-full truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        {{-- Above the bottom bar on phones. --}}
        <x-flash class="bottom-24 md:bottom-14" />
    </div>
@endsection
