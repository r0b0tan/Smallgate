@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')
@section('subheader', 'Überblick über Kunden, Projekte und Vorschauen.')

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="sg-card">
            <p class="text-sm text-white/40">Kunden</p>
            <p class="mt-2 font-display text-3xl font-bold text-white">{{ $customerCount }}</p>
            <p class="mt-1 text-xs text-white/35">{{ $activeCustomerCount }} aktiv</p>
        </div>
        <div class="sg-card">
            <p class="text-sm text-white/40">Projekte</p>
            <p class="mt-2 font-display text-3xl font-bold text-white">{{ $projectCount }}</p>
            <p class="mt-1 text-xs text-white/35">{{ $activeProjectCount }} aktiv</p>
        </div>
        <div class="sg-card">
            <p class="text-sm text-white/40">Vorschauen</p>
            <p class="mt-2 font-display text-3xl font-bold text-white">{{ $previewCount }}</p>
            <p class="mt-1 text-xs text-white/35">Basisdomain: {{ config('previews.base_domain') }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="sg-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-white">Neueste Projekte</h2>
                <a href="{{ route('admin.projects.index') }}" class="text-sm text-accent hover:text-accent-soft">Alle</a>
            </div>

            @if ($recentProjects->isEmpty())
                <div class="mt-4"><x-empty message="Noch keine Projekte angelegt." /></div>
            @else
                <ul class="mt-4 divide-y divide-white/5">
                    @foreach ($recentProjects as $project)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.projects.show', $project) }}"
                                   class="block truncate text-sm font-medium text-white hover:text-accent">
                                    {{ $project->name }}
                                </a>
                                <p class="truncate text-xs text-white/35">{{ $project->customer->name }}</p>
                            </div>
                            <span class="sg-badge {{ $project->status->badgeClasses() }}">
                                {{ $project->status->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="sg-card">
            <h2 class="font-display text-lg font-semibold text-white">Offene Einladungen</h2>

            @if ($pendingInvitations->isEmpty())
                <div class="mt-4"><x-empty message="Keine offenen Einladungen." /></div>
            @else
                <ul class="mt-4 divide-y divide-white/5">
                    @foreach ($pendingInvitations as $invitation)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm text-white">{{ $invitation->email }}</p>
                                <p class="truncate text-xs text-white/35">
                                    {{ $invitation->customer?->name }}
                                </p>
                            </div>
                            <span class="whitespace-nowrap text-xs text-white/35">
                                bis {{ $invitation->expires_at->format('d.m.Y H:i') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
