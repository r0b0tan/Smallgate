@extends('layouts.app')

@use('App\Enums\FeedbackDecision')

@section('title', 'Ihre Nachrichten')
@section('header', 'Ihre Nachrichten')
@section('subheader', 'Alle Rückmeldungen zu Ihren Entwürfen, die neueste zuerst.')

@php
    $tz = config('smallgate.display_timezone');
    $contactEmail = (string) config('smallgate.contact_email');
@endphp

{{-- Replies come by mail, so the way to write one is a mail as well. --}}
@if ($contactEmail !== '')
    @section('actions')
        <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Kundenportal – '.(auth()->user()->customer?->name ?? auth()->user()->name)) }}"
           class="sg-btn-primary">
            <x-icon name="mail" class="size-4" /> Nachricht per E-Mail
        </a>
    @endsection
@endif

@section('content')
    @if ($feedback->isEmpty())
        <div class="sg-card px-6 py-12 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-md bg-brand-soft text-brand">
                <x-icon name="chat" class="size-6" />
            </span>
            <p class="mt-4 text-lg font-semibold">Noch keine Nachrichten.</p>
            <p class="mt-1 sg-muted">Sobald Sie einen Entwurf freigeben oder eine Änderung wünschen, steht es hier.</p>
        </div>
    @else
        <ol class="divide-y divide-line overflow-hidden sg-card p-0">
            @foreach ($feedback as $entry)
                @php
                    $approved = $entry->decision === FeedbackDecision::Approved;
                    $projectUrl = route('portal.projects.show', $entry->preview->project);
                @endphp
                <li class="flex gap-4 px-5 py-5 sm:px-6">
                    <span @class(['mt-0.5', 'text-brand' => $approved, 'text-amber-700' => ! $approved])>
                        <x-icon :name="$approved ? 'check-circle' : 'edit-circle'" class="size-7" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <p class="font-semibold">
                                {{ $entry->user->name }}
                                @if ($entry->user_id === auth()->id())
                                    <span class="font-normal sg-muted">(Sie)</span>
                                @endif
                            </p>
                            <time datetime="{{ $entry->created_at->toIso8601String() }}" class="text-sm text-ink">
                                {{ $entry->created_at->copy()->timezone($tz)->format('d.m.Y, H:i') }} Uhr
                            </time>
                        </div>

                        <p class="mt-1 text-sm">
                            <span @class(['font-medium', 'text-brand' => $approved, 'text-amber-800' => ! $approved])>
                                {{ $approved ? 'Passt so!' : 'Änderung gewünscht' }}
                            </span>
                            <span class="sg-muted">·</span>
                            <a href="{{ $projectUrl }}" class="sg-link">{{ $entry->preview->project->name }}</a>
                            <span class="sg-muted" aria-hidden="true">›</span>
                            {{-- The draft's card on the project page, not the live address. --}}
                            <a href="{{ $projectUrl }}#entwurf-{{ $entry->preview->id }}" class="sg-link">„{{ $entry->preview->name }}“</a>
                            <span class="sg-muted">(Fassung {{ $entry->preview_version }})</span>
                        </p>

                        @if ($entry->comment)
                            <blockquote class="mt-3 whitespace-pre-line rounded-md bg-brand-tint px-4 py-3 text-ink ring-1 ring-inset ring-line">{{ $entry->comment }}</blockquote>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="mt-6">{{ $feedback->links() }}</div>
    @endif
@endsection
