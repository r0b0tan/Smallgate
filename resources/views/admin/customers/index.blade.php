@extends('layouts.app')

@section('title', 'Kunden')
@section('header', 'Kunden')
@section('subheader', 'Alle Kunden mit ihren Projekten und Zugängen.')

@section('actions')
    <a href="{{ route('admin.customers.create') }}" class="sg-btn-primary">Kunde anlegen</a>
@endsection

@section('content')
    @if ($customers->isEmpty())
        <x-empty message="Noch keine Kunden angelegt." />
    @else
        <div class="overflow-x-auto rounded-xl ring-1 ring-white/5">
            <table class="min-w-full divide-y divide-white/5 text-sm">
                <thead class="bg-surface text-left text-xs uppercase tracking-wide text-white/40">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Kürzel</th>
                        <th class="px-4 py-3 font-medium">Projekte</th>
                        <th class="px-4 py-3 font-medium">Zugänge</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"><span class="sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 bg-surface/40">
                    @foreach ($customers as $customer)
                        <tr class="hover:bg-white/[0.02]">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.customers.show', $customer) }}"
                                   class="font-medium text-white hover:text-accent">{{ $customer->name }}</a>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-white/45">{{ $customer->slug }}</td>
                            <td class="px-4 py-3 text-white/60">{{ $customer->projects_count }}</td>
                            <td class="px-4 py-3 text-white/60">{{ $customer->users_count }}</td>
                            <td class="px-4 py-3">
                                <span class="sg-badge {{ $customer->is_active
                                    ? 'bg-accent/10 text-accent ring-accent/30'
                                    : 'bg-white/5 text-white/40 ring-white/10' }}">
                                    {{ $customer->is_active ? 'Aktiv' : 'Deaktiviert' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.customers.edit', $customer) }}"
                                   class="text-xs text-white/50 hover:text-accent">Bearbeiten</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $customers->links() }}</div>
    @endif
@endsection
