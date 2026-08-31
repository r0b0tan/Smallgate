@extends('layouts.app')

@section('title', 'Vorschauen')
@section('breadcrumb')
    <a href="{{ route('admin.projects.index') }}" class="hover:text-white/60">Projekte</a> /
    <a href="{{ route('admin.projects.show', $project) }}" class="hover:text-white/60">{{ $project->name }}</a>
@endsection
@section('header', 'Vorschauen')
@section('subheader', $project->customer->name . ' · ' . $project->name)

@section('actions')
    <a href="{{ route('admin.projects.previews.create', $project) }}" class="sg-btn-primary">Vorschau anlegen</a>
@endsection

@section('content')
    <div class="mb-6 rounded-xl bg-surface/60 px-4 py-3 text-xs text-white/40 ring-1 ring-white/5">
        Vorschauen werden im MVP nur als geschützter Eintrag im Portal geführt. Die tatsächliche
        Auslieferung über <span class="font-mono text-white/60">*.{{ config('previews.base_domain') }}</span>
        folgt in einer eigenen Phase – siehe ADR 0001.
    </div>

    @if ($previews->isEmpty())
        <x-empty message="Für dieses Projekt gibt es noch keine Vorschauen." />
    @else
        <div class="space-y-4">
            @foreach ($previews as $preview)
                <div class="sg-card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-semibold text-white">{{ $preview->name }}</h2>
                            <p class="mt-1 font-mono text-xs text-white/40">
                                {{ $preview->hostname ?? 'kein Hostname hinterlegt' }}
                            </p>
                        </div>
                        <span class="sg-badge {{ $preview->status->badgeClasses() }}">
                            {{ $preview->status->label() }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-white/40">Zieltyp</dt>
                            <dd class="text-white/70">{{ $preview->target_type->label() }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-white/40">Ziel</dt>
                            {{-- Only ever shown to administrators. --}}
                            <dd class="truncate font-mono text-xs text-white/70">{{ $preview->target ?? '–' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-white/5 pt-4">
                        <a href="{{ route('admin.projects.previews.edit', [$project, $preview]) }}"
                           class="sg-btn-secondary">Bearbeiten</a>

                        <form method="POST"
                              action="{{ route('admin.projects.previews.provision', [$project, $preview]) }}">
                            @csrf
                            <button type="submit" class="sg-btn-secondary">Bereitstellen</button>
                        </form>

                        <form method="POST"
                              action="{{ route('admin.projects.previews.destroy', [$project, $preview]) }}"
                              onsubmit="return confirm('Diese Vorschau wirklich löschen?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sg-btn-danger">Löschen</button>
                        </form>

                        @if ($preview->provisioned_at)
                            <span class="self-center text-xs text-white/30">
                                zuletzt bereitgestellt {{ $preview->provisioned_at->format('d.m.Y H:i') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
