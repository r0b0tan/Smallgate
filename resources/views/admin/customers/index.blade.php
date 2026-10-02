@extends('layouts.app')

@section('title', 'Kunden')
@section('header', 'Kunden')
@section('subheader', 'Alle Kunden mit ihren Projekten und Zugängen.')

@section('actions')
    <a href="{{ route('admin.customers.create') }}" class="sg-btn-primary">Kunde anlegen</a>
@endsection

@section('content')
    @if ($customers->isEmpty())
        <x-empty message="Noch keine Kunden angelegt.">
            <a href="{{ route('admin.customers.create') }}" class="sg-btn-primary">Kunde anlegen</a>
        </x-empty>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-card ring-1 ring-line">
            <table class="sg-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Kürzel</th>
                        <th class="px-4 py-3 font-medium">Projekte</th>
                        <th class="px-4 py-3 font-medium">Zugänge</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"><span class="sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr class="hover:bg-brand-tint">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.customers.show', $customer) }}"
                                   class="font-medium text-ink hover:text-brand">{{ $customer->name }}</a>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs sg-muted">{{ $customer->slug }}</td>
                            <td class="px-4 py-3 sg-muted">{{ $customer->projects_count }}</td>
                            <td class="px-4 py-3 sg-muted">{{ $customer->users_count }}</td>
                            <td class="px-4 py-3">
                                <span class="sg-badge {{ $customer->is_active
                                    ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                    : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                    {{ $customer->is_active ? 'Aktiv' : 'Deaktiviert' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.customers.edit', $customer) }}"
                                   class="text-xs sg-faint hover:text-brand">Bearbeiten</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $customers->links() }}</div>
    @endif
@endsection
