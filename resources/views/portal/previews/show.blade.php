@extends('layouts.app')

@section('title', $preview->name)
@section('breadcrumb')
    <a href="{{ route('portal.dashboard') }}" class="hover:text-white/60">Meine Projekte</a> /
    <a href="{{ route('portal.projects.show', $project) }}" class="hover:text-white/60">{{ $project->name }}</a>
@endsection
@section('header', $preview->name)
@section('subheader', 'Vorschau im Projekt ' . $project->name)

@section('content')
    <div class="max-w-2xl sg-card">
        <dl class="space-y-4 text-sm">
            <div>
                <dt class="text-white/40">Status</dt>
                <dd class="mt-1">
                    <span class="sg-badge {{ $preview->status->badgeClasses() }}">
                        {{ $preview->status->label() }}
                    </span>
                </dd>
            </div>

            <div>
                <dt class="text-white/40">Adresse</dt>
                <dd class="mt-1 font-mono text-xs text-white/70">{{ $preview->hostname ?? '–' }}</dd>
            </div>
        </dl>

        {{-- The customer only ever sees the hostname, never the target path or
             upstream URL behind it. --}}
        @if ($url = $preview->url())
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
               class="sg-btn-primary mt-6 w-full">Vorschau öffnen</a>
            <p class="mt-3 text-xs text-white/35">
                Die Vorschau ist ausschließlich für Sie bestimmt. Bitte geben Sie den Link nicht weiter.
            </p>
        @else
            <p class="mt-6 rounded-lg bg-white/5 px-4 py-3 text-sm text-white/50">
                Diese Vorschau ist derzeit nicht erreichbar. Wir melden uns, sobald sie bereitsteht.
            </p>
        @endif
    </div>
@endsection
