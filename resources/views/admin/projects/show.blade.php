@extends('layouts.app')

@section('title', $project->name)
@section('breadcrumb')
    <a href="{{ route('admin.projects.index') }}" class="hover:text-white/60">Projekte</a> /
    <a href="{{ route('admin.customers.show', $project->customer) }}" class="hover:text-white/60">
        {{ $project->customer->name }}
    </a>
@endsection
@section('header', $project->name)
@section('subheader', $project->status->label())

@section('actions')
    <a href="{{ route('admin.projects.previews.index', $project) }}" class="sg-btn-secondary">Vorschauen</a>
    <a href="{{ route('admin.projects.edit', $project) }}" class="sg-btn-primary">Bearbeiten</a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="sg-card lg:col-span-2">
            <h2 class="font-display text-lg font-semibold text-white">Beschreibung</h2>
            <p class="mt-3 whitespace-pre-line text-sm text-white/60">
                {{ $project->description ?: 'Keine Beschreibung hinterlegt.' }}
            </p>
        </div>

        <div class="sg-card">
            <h2 class="font-display text-lg font-semibold text-white">Details</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-white/40">Kunde</dt>
                    <dd class="text-white/70">{{ $project->customer->name }}</dd>
                </div>
                <div>
                    <dt class="text-white/40">Kürzel</dt>
                    <dd class="font-mono text-xs text-white/70">{{ $project->slug }}</dd>
                </div>
                <div>
                    <dt class="text-white/40">Vorschauen</dt>
                    <dd class="text-white/70">{{ $previews->count() }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6 sg-card">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-white">Vorschauen</h2>
            <a href="{{ route('admin.projects.previews.create', $project) }}"
               class="text-sm text-accent hover:text-accent-soft">Vorschau anlegen</a>
        </div>

        @if ($previews->isEmpty())
            <div class="mt-4"><x-empty message="Für dieses Projekt gibt es noch keine Vorschauen." /></div>
        @else
            <ul class="mt-4 divide-y divide-white/5">
                @foreach ($previews as $preview)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-white">{{ $preview->name }}</p>
                            <p class="truncate font-mono text-xs text-white/35">
                                {{ $preview->hostname ?? 'kein Hostname' }}
                            </p>
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
