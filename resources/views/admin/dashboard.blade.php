@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')
@section('subheader', 'Was offen ist.')

@section('actions')
    <a href="{{ route('admin.customers.create') }}" class="sg-btn-secondary">Kunde anlegen</a>
    <a href="{{ route('admin.projects.create') }}" class="sg-btn-primary">Projekt anlegen</a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="sg-card">
            <h2 class="text-lg font-semibold">Offene Vorschauen</h2>
            <p class="mt-1 text-xs sg-faint">
                Entwürfe, fehlgeschlagene Bereitstellungen und Änderungen, die noch nicht live sind.
            </p>

            @if ($openPreviews->isEmpty())
                <div class="mt-4"><x-empty message="Nichts offen – alle Vorschauen sind auf dem aktuellen Stand." /></div>
            @else
                <ul class="mt-4 divide-y divide-line">
                    @foreach ($openPreviews as $preview)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.projects.show', $preview->project) }}"
                                   class="block truncate text-sm font-medium text-ink hover:text-brand">
                                    {{ $preview->name }}
                                </a>
                                <p class="truncate text-xs sg-faint">
                                    {{ $preview->project->customer->name }} · {{ $preview->project->name }}
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

        <div class="sg-card">
            <h2 class="text-lg font-semibold">Offene Einladungen</h2>
            <p class="mt-1 text-xs sg-faint">Versendet, aber noch nicht eingelöst.</p>

            @if ($pendingInvitations->isEmpty())
                <div class="mt-4"><x-empty message="Keine offenen Einladungen." /></div>
            @else
                <ul class="mt-4 divide-y divide-line">
                    @foreach ($pendingInvitations as $invitation)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm">{{ $invitation->email }}</p>
                                <p class="truncate text-xs sg-faint">{{ $invitation->customer?->name }}</p>
                            </div>
                            <span class="whitespace-nowrap text-xs sg-faint">
                                bis {{ $invitation->expires_at->format('d.m.Y H:i') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="mt-6 sg-card">
        <h2 class="text-lg font-semibold">Neueste Rückmeldungen</h2>
        <p class="mt-1 text-xs sg-faint">Was Kunden zu ihren Entwürfen gesagt haben.</p>

        @if ($recentFeedback->isEmpty())
            <div class="mt-4"><x-empty message="Noch keine Rückmeldungen." /></div>
        @else
            <ul class="mt-4 divide-y divide-line">
                @foreach ($recentFeedback as $feedback)
                    <li class="flex flex-wrap items-start justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.projects.show', $feedback->preview->project) }}"
                               class="block truncate text-sm font-medium text-ink hover:text-brand">
                                {{ $feedback->preview->name }}
                                <span class="sg-faint">· Version {{ $feedback->preview_version }}</span>
                            </a>
                            <p class="truncate text-xs sg-faint">
                                {{ $feedback->preview->project->customer->name }} · {{ $feedback->user->name }} ·
                                {{ $feedback->created_at->timezone(config('smallgate.display_timezone'))->format('d.m.Y H:i') }}
                            </p>
                            @if ($feedback->comment)
                                <p class="mt-1 line-clamp-2 text-sm text-ink">{{ $feedback->comment }}</p>
                            @endif
                        </div>
                        <span @class([
                            'sg-badge',
                            'bg-emerald-50 text-emerald-800 ring-emerald-200' => $feedback->decision === \App\Enums\FeedbackDecision::Approved,
                            'bg-amber-50 text-amber-900 ring-amber-200' => $feedback->decision === \App\Enums\FeedbackDecision::ChangesRequested,
                        ])>{{ $feedback->decision->label() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6 sg-card">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Zuletzt angelegte Projekte</h2>
            <a href="{{ route('admin.projects.index') }}" class="text-sm sg-link">Alle</a>
        </div>

        @if ($recentProjects->isEmpty())
            <div class="mt-4">
                <x-empty message="Noch keine Projekte angelegt.">
                    <a href="{{ route('admin.projects.create') }}" class="sg-btn-primary">Projekt anlegen</a>
                </x-empty>
            </div>
        @else
            <ul class="mt-4 divide-y divide-line">
                @foreach ($recentProjects as $project)
                    <li class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.projects.show', $project) }}"
                               class="block truncate text-sm font-medium text-ink hover:text-brand">
                                {{ $project->name }}
                            </a>
                            <p class="truncate text-xs sg-faint">{{ $project->customer->name }}</p>
                        </div>
                        <span class="sg-badge {{ $project->status->badgeClasses() }}">
                            {{ $project->status->label() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
