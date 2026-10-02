@extends('layouts.app')

{{-- The customer's one page: per project its status and every draft, the one
     waiting for an answer first, and beside it the last few answers. --}}

@section('title', 'Ihr Projektstatus')

@php
    $tz = config('smallgate.display_timezone');

    foreach ($projects as $project) {
        $project->setRelation('previews', $project->previews
            ->each(fn ($preview) => $preview->setRelation('project', $project))
            ->sortBy([
                fn ($a, $b) => $b->awaitsFeedback() <=> $a->awaitsFeedback(),
                fn ($a, $b) => $b->lastUpdatedAt() <=> $a->lastUpdatedAt(),
            ])
            ->values());
    }

    // Something to answer first, then something to look at, then the rest.
    $projects = $projects->sortBy([
        fn ($a, $b) => $b->previews->filter->awaitsFeedback()->count() <=> $a->previews->filter->awaitsFeedback()->count(),
        fn ($a, $b) => $b->previews->isNotEmpty() <=> $a->previews->isNotEmpty(),
        fn ($a, $b) => $b->previews->max(fn ($preview) => $preview->lastUpdatedAt()) <=> $a->previews->max(fn ($preview) => $preview->lastUpdatedAt()),
    ])->values();

    $previews = $projects->flatMap->previews;
    $newCount = $previews->filter->awaitsFeedback()->count();
    $offeredIds = $previews->pluck('id')->all();
@endphp

@section('content')
    <x-errors />

    @if ($projects->isEmpty())
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Ihr Projektstatus</h1>

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            <div class="sg-card px-6 py-12 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-md bg-brand-soft text-brand">
                    <x-icon name="clock" class="size-6" />
                </span>
                <p class="mt-4 text-lg font-semibold">Gerade ist nichts für Sie freigegeben.</p>
                <p class="mt-1 sg-muted">Wir schreiben Ihnen eine E-Mail, sobald es etwas zu sehen gibt.</p>
            </div>

            @include('portal._recent-feedback')
        </div>
    @endif

    @foreach ($projects as $project)
        <section @class(['mt-14' => ! $loop->first]) aria-labelledby="projekt-{{ $project->id }}">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <h1 id="projekt-{{ $project->id }}" class="text-2xl font-semibold tracking-tight sm:text-[1.75rem]">
                    Ihr Projektstatus
                    <span class="mx-1.5 font-normal text-ink-muted" aria-hidden="true">/</span>
                    <span class="sr-only">:</span>
                    {{ $project->name }}
                </h1>
                <span class="sg-badge {{ $project->status->badgeClasses() }}">
                    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                    {{ $project->status->label() }}
                </span>
            </div>

            {{-- The verdict, when there is something to do: the one thing to
                 know before reading anything else. --}}
            @if ($loop->first && $newCount > 1)
                <p class="mt-3 flex items-center gap-2 text-base font-medium text-amber-900" role="status">
                    <span class="size-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                    {{ $newCount }} Entwürfe warten auf Ihre Meinung
                </p>
            @elseif ($loop->first && $newCount === 1)
                <p class="mt-3 flex items-center gap-2 text-base font-medium text-amber-900" role="status">
                    <span class="size-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                    Ein Entwurf wartet auf Ihre Meinung
                </p>
            @endif

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="space-y-6">
                    @forelse ($project->previews as $preview)
                        @include('portal._draft-card', ['preview' => $preview, 'project' => $project, 'current' => $loop->first])
                    @empty
                        @php($done = in_array($project->status, [\App\Enums\ProjectStatus::Completed, \App\Enums\ProjectStatus::Archived], true))
                        <div class="sg-card flex items-center gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-md bg-brand-soft text-brand">
                                <x-icon :name="$done ? 'check' : 'clock'" />
                            </span>
                            <p class="sg-muted">
                                {{ $done ? 'Abgeschlossen – vielen Dank für die Zusammenarbeit.' : 'In Arbeit – wir schreiben Ihnen, sobald es etwas zu sehen gibt.' }}
                            </p>
                        </div>
                    @endforelse
                </div>

                {{-- Once per page, beside the first project. --}}
                @if ($loop->first)
                    @include('portal._recent-feedback')
                @endif
            </div>
        </section>
    @endforeach
@endsection
