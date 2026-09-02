@extends('layouts.app')

@section('title', $project->name)
@section('breadcrumb')
    <a href="{{ route('admin.projects.index') }}" class="hover:text-white">Projekte</a> /
    <a href="{{ route('admin.customers.show', $project->customer) }}" class="hover:text-white">
        {{ $project->customer->name }}
    </a>
@endsection
@section('header', $project->name)
@section('subheader', $project->status->label())

@section('actions')
    <a href="{{ route('admin.projects.edit', $project) }}" class="sg-btn-secondary">Bearbeiten</a>
    <a href="{{ route('admin.projects.previews.create', $project) }}" class="sg-btn-primary">Vorschau anlegen</a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="sg-card lg:col-span-2">
            <h2 class="text-lg font-semibold text-white">Beschreibung</h2>
            <p class="mt-3 whitespace-pre-line text-sm sg-muted">
                {{ $project->description ?: 'Keine Beschreibung hinterlegt.' }}
            </p>
        </div>

        <div class="sg-card">
            <h2 class="text-lg font-semibold text-white">Details</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="sg-muted">Kunde</dt>
                    <dd class="text-white/70">{{ $project->customer->name }}</dd>
                </div>
                <div>
                    <dt class="sg-muted">Kürzel</dt>
                    <dd class="font-mono text-xs text-white/70">{{ $project->slug }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- The one place previews are listed and managed. --}}
    <div class="mt-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-semibold text-white">Vorschauen</h2>
            <p class="text-xs sg-faint">
                Auslieferung über <span class="font-mono">*.{{ config('previews.base_domain') }}</span> folgt in
                einer eigenen Phase – siehe ADR 0001.
            </p>
        </div>

        @if ($previews->isEmpty())
            <x-empty message="Für dieses Projekt gibt es noch keine Vorschauen.">
                <a href="{{ route('admin.projects.previews.create', $project) }}" class="sg-btn-primary">
                    Vorschau anlegen
                </a>
            </x-empty>
        @else
            <div class="space-y-4">
                @foreach ($previews as $preview)
                    <div class="sg-card">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-white">{{ $preview->name }}</h3>
                                {{-- Administrators may open a preview in any status, not just an available one. --}}
                                @if ($url = $preview->hostUrl())
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                       class="mt-1 block font-mono text-xs sg-muted hover:text-accent">
                                        {{ $preview->hostname }}
                                    </a>
                                @else
                                    <p class="mt-1 font-mono text-xs sg-faint">kein Hostname hinterlegt</p>
                                @endif
                            </div>
                            <span class="sg-badge {{ $preview->status->badgeClasses() }}">
                                {{ $preview->status->label() }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="sg-muted">Zieltyp</dt>
                                <dd class="text-white/70">{{ $preview->target_type->label() }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="sg-muted">Ziel</dt>
                                {{-- Only ever shown to administrators. --}}
                                <dd class="truncate font-mono text-xs text-white/70">{{ $preview->target ?? '–' }}</dd>
                            </div>
                        </dl>

                        @if ($preview->status === \App\Enums\PreviewStatus::Available && $preview->needsProvisioning())
                            <p class="mt-4 rounded-lg bg-amber-400/10 px-3 py-2 text-xs text-amber-200
                                      ring-1 ring-inset ring-amber-400/30">
                                Seit der letzten Bereitstellung geändert – zum Übernehmen erneut bereitstellen.
                            </p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-white/5 pt-4">
                            <form method="POST"
                                  action="{{ route('admin.projects.previews.provision', [$project, $preview]) }}">
                                @csrf
                                <button type="submit" class="sg-btn-primary">
                                    {{ $preview->status->provisionActionLabel() }}
                                </button>
                            </form>

                            @if ($preview->status === \App\Enums\PreviewStatus::Available)
                                <form method="POST"
                                      action="{{ route('admin.projects.previews.disable', [$project, $preview]) }}">
                                    @csrf
                                    <button type="submit" class="sg-btn-secondary">Deaktivieren</button>
                                </form>
                            @endif

                            <a href="{{ route('admin.projects.previews.edit', [$project, $preview]) }}"
                               class="sg-btn-secondary">Bearbeiten</a>

                            <form method="POST" class="ms-auto"
                                  action="{{ route('admin.projects.previews.destroy', [$project, $preview]) }}"
                                  onsubmit="return confirm('Vorschau „{{ $preview->name }}“ endgültig löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sg-btn-danger">Löschen</button>
                            </form>
                        </div>

                        <p class="mt-3 text-xs sg-faint">
                            @if ($preview->provisioned_at)
                                Zuletzt bereitgestellt {{ $preview->provisioned_at->format('d.m.Y H:i') }}.
                            @else
                                Noch nie bereitgestellt.
                            @endif
                            @if ($preview->status !== \App\Enums\PreviewStatus::Available)
                                Für den Kunden derzeit nicht sichtbar.
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
