@extends('layouts.app')

@section('title', $customer->name)
@section('breadcrumb')
    <a href="{{ route('admin.customers.index') }}" class="hover:text-white/60">Kunden</a>
@endsection
@section('header', $customer->name)
@section('subheader', $customer->is_active ? 'Aktiver Kunde' : 'Deaktivierter Kunde')

@section('actions')
    <a href="{{ route('admin.customers.users.index', $customer) }}" class="sg-btn-secondary">Zugänge verwalten</a>
    <a href="{{ route('admin.customers.edit', $customer) }}" class="sg-btn-secondary">Bearbeiten</a>
    <a href="{{ route('admin.projects.create', ['customer' => $customer->id]) }}" class="sg-btn-primary">
        Projekt anlegen
    </a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="sg-card lg:col-span-1">
            <h2 class="font-display text-lg font-semibold text-white">Stammdaten</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-white/40">Kürzel</dt>
                    <dd class="font-mono text-xs text-white/70">{{ $customer->slug }}</dd>
                </div>
                <div>
                    <dt class="text-white/40">Kontakt-E-Mail</dt>
                    <dd class="text-white/70">{{ $customer->contact_email ?? '–' }}</dd>
                </div>
                <div>
                    <dt class="text-white/40">Zugänge</dt>
                    <dd class="text-white/70">{{ $users->count() }}</dd>
                </div>
                <div>
                    <dt class="text-white/40">Angelegt</dt>
                    <dd class="text-white/70">{{ $customer->created_at->format('d.m.Y') }}</dd>
                </div>
            </dl>
        </div>

        <div class="sg-card lg:col-span-2">
            <h2 class="font-display text-lg font-semibold text-white">Projekte</h2>

            @if ($customer->projects->isEmpty())
                <div class="mt-4"><x-empty message="Für diesen Kunden gibt es noch keine Projekte." /></div>
            @else
                <ul class="mt-4 divide-y divide-white/5">
                    @foreach ($customer->projects as $project)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.projects.show', $project) }}"
                                   class="block truncate text-sm font-medium text-white hover:text-accent">
                                    {{ $project->name }}
                                </a>
                                <p class="truncate font-mono text-xs text-white/35">{{ $project->slug }}</p>
                            </div>
                            <span class="sg-badge {{ $project->status->badgeClasses() }}">
                                {{ $project->status->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
