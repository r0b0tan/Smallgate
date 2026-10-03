@extends('layouts.app')

@section('title', $project->name)
@section('breadcrumb')
    <a href="{{ route('admin.projects.index') }}" class="hover:text-ink">Projekte</a> /
    <a href="{{ route('admin.customers.show', $project->customer) }}" class="hover:text-ink">
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
            <h2 class="text-lg font-semibold">Beschreibung</h2>
            <p class="mt-3 whitespace-pre-line text-sm sg-muted">
                {{ $project->description ?: 'Keine Beschreibung hinterlegt.' }}
            </p>
        </div>

        <div class="sg-card">
            <h2 class="text-lg font-semibold">Details</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="sg-muted">Kunde</dt>
                    <dd class="text-ink">{{ $project->customer->name }}</dd>
                </div>
                <div>
                    <dt class="sg-muted">Kürzel</dt>
                    <dd class="font-mono text-xs text-ink">{{ $project->slug }}</dd>
                </div>
                @include('admin.projects._directory')
            </dl>
        </div>
    </div>

    {{-- The one place previews are listed and managed. --}}
    <div class="mt-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-semibold">Vorschauen</h2>
            <p class="text-xs sg-faint">
                Statische Vorschauen liefert Smallgate unter
                <span class="font-mono">*.{{ config('previews.base_domain') }}</span> aus, nur für angemeldete Benutzer.
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
                                <h3 class="text-base font-semibold">{{ $preview->name }}</h3>
                                {{-- Administrators may open a preview in any status, not just an available one. --}}
                                @if ($url = $preview->openUrl())
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                       class="mt-1 block font-mono text-xs sg-muted hover:text-brand">
                                        {{ $preview->displayAddress() }}
                                    </a>
                                @else
                                    <p class="mt-1 font-mono text-xs sg-faint">keine Adresse hinterlegt</p>
                                @endif
                            </div>
                            <span class="sg-badge {{ $preview->status->badgeClasses() }}">
                                {{ $preview->status->label() }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="sg-muted">Zieltyp</dt>
                                <dd class="text-ink">{{ $preview->target_type->label() }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="sg-muted">Ziel</dt>
                                {{-- Shown to administrators. A directory path never reaches a
                                     customer; an upstream URL is the address they open. --}}
                                <dd class="truncate font-mono text-xs text-ink">{{ $preview->target ?? '–' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 grid gap-4 border-t border-line pt-4 sm:grid-cols-[14rem_minmax(0,1fr)]">
                            <div>
                                <div class="relative aspect-[16/10] overflow-hidden rounded-md bg-paper ring-1 ring-line">
                                    @if ($preview->thumbnail_path)
                                        <img src="{{ route('admin.projects.previews.thumbnail', [$project, $preview, 'v' => $preview->thumbnail_generated_at?->timestamp]) }}"
                                             alt="Vorschaubild {{ $preview->name }}" loading="lazy"
                                             @class(['size-full object-cover object-top', 'opacity-40' => $preview->hasStaleThumbnail()])>
                                    @else
                                        <div class="flex size-full items-center justify-center p-3 text-center text-xs sg-faint">
                                            Kein Vorschaubild
                                        </div>
                                    @endif
                                    @if ($preview->hasStaleThumbnail())
                                        <span class="absolute inset-x-2 bottom-2 rounded bg-amber-400 px-2 py-1 text-center
                                                     text-xs font-semibold text-amber-950">
                                            Veraltet (Version {{ $preview->thumbnail_version }})
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 text-xs sg-faint">
                                    Version {{ $preview->version }}
                                    @if ($preview->thumbnail_status)
                                        · Vorschaubild: {{ $preview->thumbnail_status->label() }}
                                    @endif
                                </p>
                            </div>

                            <div class="min-w-0">
                                <h4 class="text-sm font-semibold">Rückmeldungen</h4>
                                @if ($preview->feedback->isEmpty())
                                    <p class="mt-2 text-sm sg-faint">
                                        {{ $preview->status === \App\Enums\PreviewStatus::Available ? 'Noch keine Rückmeldung des Kunden.' : 'Keine.' }}
                                    </p>
                                @else
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($preview->feedback->take(5) as $feedback)
                                            <li @class(['text-sm', 'opacity-60' => $feedback->preview_version !== $preview->version])>
                                                <span @class([
                                                    'sg-badge',
                                                    'bg-emerald-50 text-emerald-800 ring-emerald-200' => $feedback->decision === \App\Enums\FeedbackDecision::Approved,
                                                    'bg-amber-50 text-amber-900 ring-amber-200' => $feedback->decision === \App\Enums\FeedbackDecision::ChangesRequested,
                                                ])>{{ $feedback->decision->label() }}</span>
                                                <span class="text-xs sg-faint">
                                                    Version {{ $feedback->preview_version }} ·
                                                    {{ $feedback->user->name }} ·
                                                    {{ $feedback->created_at->timezone(config('smallgate.display_timezone'))->format('d.m.Y H:i') }}
                                                </span>
                                                @if ($feedback->comment)
                                                    <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $feedback->comment }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        @if ($preview->status === \App\Enums\PreviewStatus::Available && $preview->needsProvisioning())
                            <p class="mt-4 sg-alert-notice">
                                Seit der letzten Bereitstellung geändert – zum Übernehmen erneut bereitstellen.
                            </p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
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

                                <form method="POST"
                                      action="{{ route('admin.projects.previews.thumbnail.regenerate', [$project, $preview]) }}">
                                    @csrf
                                    <button type="submit" class="sg-btn-secondary">Vorschaubild neu erstellen</button>
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
