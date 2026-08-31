@extends('layouts.app')

@section('title', 'Projekte')
@section('header', 'Projekte')
@section('subheader', 'Alle Projekte über alle Kunden hinweg.')

@section('actions')
    <a href="{{ route('admin.projects.create') }}" class="sg-btn-primary">Projekt anlegen</a>
@endsection

@section('content')
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <div class="w-56">
            <x-select name="customer" label="Kunde" placeholder="Alle Kunden"
                      :value="request('customer')"
                      :options="$customers->pluck('name', 'id')->all()" />
        </div>
        <div class="w-56">
            <x-select name="status" label="Status" placeholder="Alle Status"
                      :value="request('status')" :options="$statuses" />
        </div>
        <button type="submit" class="sg-btn-secondary">Filtern</button>
    </form>

    @if ($projects->isEmpty())
        <x-empty message="Keine Projekte gefunden." />
    @else
        <div class="overflow-x-auto rounded-xl ring-1 ring-white/5">
            <table class="min-w-full divide-y divide-white/5 text-sm">
                <thead class="bg-surface text-left text-xs uppercase tracking-wide text-white/40">
                    <tr>
                        <th class="px-4 py-3 font-medium">Projekt</th>
                        <th class="px-4 py-3 font-medium">Kunde</th>
                        <th class="px-4 py-3 font-medium">Vorschauen</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"><span class="sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 bg-surface/40">
                    @foreach ($projects as $project)
                        <tr class="hover:bg-white/[0.02]">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.projects.show', $project) }}"
                                   class="font-medium text-white hover:text-accent">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-white/60">
                                <a href="{{ route('admin.customers.show', $project->customer) }}"
                                   class="hover:text-accent">{{ $project->customer->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-white/60">{{ $project->previews_count }}</td>
                            <td class="px-4 py-3">
                                <span class="sg-badge {{ $project->status->badgeClasses() }}">
                                    {{ $project->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.projects.edit', $project) }}"
                                   class="text-xs text-white/50 hover:text-accent">Bearbeiten</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
@endsection
