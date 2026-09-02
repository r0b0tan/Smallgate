@extends('layouts.app')

@section('title', $project->name)
@section('breadcrumb')
    <a href="{{ route('portal.dashboard') }}" class="hover:text-white">Meine Projekte</a>
@endsection
@section('header', $project->name)
@section('subheader', $project->status->label())

@section('content')
    @php
        // Only previews that are actually reachable are offered.
        $visible = $previews->filter(fn ($preview) => $preview->url() !== null);
    @endphp

    {{-- The previews are what the customer came for, so they come first. --}}
    <div class="sg-card">
        <h2 class="text-lg font-semibold text-white">Vorschauen</h2>

        @if ($visible->isEmpty())
            <div class="mt-4">
                <x-empty message="Derzeit ist keine Vorschau freigegeben. Wir melden uns, sobald es etwas zu sehen gibt." />
            </div>
        @else
            <ul class="mt-4 divide-y divide-white/5">
                @foreach ($visible as $preview)
                    <li>
                        {{-- The row is the link, and the link is the preview:
                             there is no page in between. --}}
                        <a href="{{ $preview->url() }}" target="_blank" rel="noopener noreferrer"
                           class="-mx-2 flex flex-wrap items-center justify-between gap-3 rounded-lg px-2 py-3
                                  transition-colors hover:bg-white/5">
                            <div class="min-w-0">
                                <span class="block truncate text-sm font-medium text-white">
                                    {{ $preview->name }}
                                </span>
                                <span class="block truncate text-xs sg-faint">
                                    Stand {{ $preview->lastUpdatedAt()->format('d.m.Y') }}
                                </span>
                            </div>
                            <span class="whitespace-nowrap text-sm font-semibold text-accent">
                                Öffnen <span aria-hidden="true">&#8599;</span>
                                <span class="sr-only">(öffnet in einem neuen Tab)</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 text-xs sg-faint">
                Die Vorschauen sind ausschließlich für Sie bestimmt. Bitte geben Sie die Links nicht weiter.
            </p>
        @endif
    </div>

    <div class="mt-6 sg-card">
        <h2 class="text-lg font-semibold text-white">Zum Projekt</h2>
        <p class="mt-3 whitespace-pre-line text-sm sg-muted">
            {{ $project->description ?: 'Für dieses Projekt liegt derzeit keine Beschreibung vor.' }}
        </p>
        <p class="mt-4 text-xs sg-faint">
            Rechnungen, Dokumente und Absprachen laufen wie gewohnt per E-Mail.
        </p>
    </div>
@endsection
