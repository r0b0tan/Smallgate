@extends('layouts.app')

@section('title', 'Protokoll')
@section('header', 'Protokoll')
@section('subheader', 'Wer hat wann was getan. Einträge werden nach '.$retentionDays.' Tagen gelöscht.')

@php
    $tz = config('smallgate.display_timezone');
@endphp

@section('content')
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <div class="w-56">
            <x-select name="customer" label="Kunde" placeholder="Alle Kunden"
                      :value="request('customer')"
                      :options="$customers->pluck('name', 'id')->all()" />
        </div>
        <div class="w-56">
            <x-select name="group" label="Bereich" placeholder="Alle Bereiche"
                      :value="request('group')" :options="$groups" />
        </div>
        <button type="submit" class="sg-btn-secondary">Filtern</button>

        @if ($filtered)
            <a href="{{ route('admin.activities.index') }}" class="sg-btn-secondary">Zurücksetzen</a>
        @endif
    </form>

    @if ($activities->isEmpty())
        @if ($filtered)
            <x-empty message="Keine Einträge passen zu diesem Filter.">
                <a href="{{ route('admin.activities.index') }}" class="sg-btn-secondary">Filter zurücksetzen</a>
            </x-empty>
        @else
            <x-empty message="Noch keine Einträge." />
        @endif
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-card ring-1 ring-line">
            <table class="sg-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">Zeitpunkt</th>
                        <th class="px-4 py-3 font-medium">Wer</th>
                        <th class="px-4 py-3 font-medium">Was</th>
                        <th class="px-4 py-3 font-medium">Betrifft</th>
                        <th class="px-4 py-3 font-medium">Kunde</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $activity)
                        @php
                            $subject = $activity->subject;
                            $subjectUrl = match (true) {
                                $subject instanceof \App\Models\Project => route('admin.projects.show', $subject),
                                $subject instanceof \App\Models\Preview => route('admin.projects.show', $subject->project_id),
                                $subject instanceof \App\Models\Customer => route('admin.customers.show', $subject),
                                ($subject instanceof \App\Models\User || $subject instanceof \App\Models\Invitation)
                                    && $subject->customer_id !== null => route('admin.customers.users.index', $subject->customer_id),
                                default => null,
                            };
                            $version = $activity->properties['version'] ?? null;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 sg-muted">
                                {{ $activity->created_at->timezone($tz)->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $activity->actor?->name ?? '–' }}
                                @if ($activity->actor?->isAdmin())
                                    <span class="text-xs sg-muted">(Admin)</span>
                                @endif
                            </td>
                            <td @class(['px-4 py-3 font-medium', 'text-red-700' => $activity->action->isWarning()])>
                                {{ $activity->action->label() }}@if ($version)<span class="font-normal sg-muted"> · Version {{ $version }}</span>@endif
                            </td>
                            <td class="px-4 py-3 sg-muted">
                                @if ($subjectUrl)
                                    <a href="{{ $subjectUrl }}" class="hover:text-brand">{{ $activity->subjectName() }}</a>
                                @else
                                    {{ $activity->subjectName() ?? '–' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 sg-muted">
                                @if ($activity->customer)
                                    <a href="{{ route('admin.customers.show', $activity->customer) }}"
                                       class="hover:text-brand">{{ $activity->customer->name }}</a>
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $activities->links() }}</div>
    @endif
@endsection
