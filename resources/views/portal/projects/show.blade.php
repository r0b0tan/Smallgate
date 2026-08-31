@extends('layouts.app')

@section('title', $project->name)
@section('breadcrumb')
    <a href="{{ route('portal.dashboard') }}" class="hover:text-white/60">Meine Projekte</a>
@endsection
@section('header', $project->name)
@section('subheader', $project->status->label())

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="sg-card lg:col-span-2">
            <h2 class="font-display text-lg font-semibold text-white">Zum Projekt</h2>
            <p class="mt-3 whitespace-pre-line text-sm text-white/60">
                {{ $project->description ?: 'Für dieses Projekt liegt derzeit keine Beschreibung vor.' }}
            </p>
        </div>

        <div class="sg-card">
            <h2 class="font-display text-lg font-semibold text-white">Status</h2>
            <p class="mt-3">
                <span class="sg-badge {{ $project->status->badgeClasses() }}">{{ $project->status->label() }}</span>
            </p>
            <p class="mt-4 text-xs text-white/35">
                Rechnungen, Dokumente und Absprachen laufen wie gewohnt per E-Mail.
            </p>
        </div>
    </div>

    <div class="mt-6 sg-card">
        <h2 class="font-display text-lg font-semibold text-white">Vorschauen</h2>

        @php
            // Only previews that are actually reachable are offered.
            $visible = $previews->filter(fn ($preview) => $preview->status->isVisitable());
        @endphp

        @if ($visible->isEmpty())
            <div class="mt-4"><x-empty message="Derzeit ist keine Vorschau freigegeben." /></div>
        @else
            <ul class="mt-4 divide-y divide-white/5">
                @foreach ($visible as $preview)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('portal.previews.show', [$project, $preview]) }}"
                               class="block truncate text-sm font-medium text-white hover:text-accent">
                                {{ $preview->name }}
                            </a>
                            <p class="truncate font-mono text-xs text-white/35">{{ $preview->hostname }}</p>
                        </div>
                        <span class="sg-badge {{ $preview->status->badgeClasses() }}">
                            {{ $preview->status->label() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
