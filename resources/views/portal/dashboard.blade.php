@extends('layouts.portal')

{{-- The customer's one page. It opens with a verdict -- is something waiting
     for me? -- then every draft as a card, unanswered ones first, then the
     projects that have nothing to show yet, then help. --}}

@section('title', 'Ihre Entwürfe')

@php
    $now = now(config('smallgate.display_timezone'))->locale('de');
    $salutation = match (true) {
        $now->hour >= 5 && $now->hour < 11 => 'Guten Morgen',
        $now->hour >= 11 && $now->hour < 18 => 'Guten Tag',
        default => 'Guten Abend',
    };

    $previews = $projects
        ->flatMap(fn ($project) => $project->previews->each(fn ($preview) => $preview->setRelation('project', $project)))
        ->sortBy([
            fn ($a, $b) => $b->awaitsFeedback() <=> $a->awaitsFeedback(),
            fn ($a, $b) => $b->lastUpdatedAt() <=> $a->lastUpdatedAt(),
        ])
        ->values();

    $newCount = $previews->filter(fn ($preview) => $preview->awaitsFeedback())->count();
    $withoutDraft = $projects->filter(fn ($project) => $project->previews->isEmpty());
@endphp

@section('content')
    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
        <div>
            <p class="text-base font-medium text-brand">{{ $now->isoFormat('dddd, D. MMMM') }}</p>
            <h1 class="mt-1 text-4xl font-semibold tracking-tight sm:text-5xl">{{ $salutation }}</h1>
            <p class="mt-3 max-w-xl text-lg sg-muted">
                Hier sehen Sie Ihre Entwürfe und sagen uns mit einem Klick, was Sie davon halten.
            </p>
        </div>

        {{-- The verdict: the one thing to know before reading anything else. --}}
        @if ($newCount > 0)
            <div class="flex items-center gap-4 rounded-2xl bg-amber-50 px-5 py-4 ring-1 ring-amber-200/80" role="status">
                <span class="relative flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-400
                             text-xl font-bold text-amber-950">
                    <span class="absolute inset-0 animate-ping rounded-full bg-amber-400 opacity-30 motion-reduce:hidden"></span>
                    <span class="relative">{{ $newCount }}</span>
                </span>
                <div>
                    <p class="text-lg font-semibold text-amber-950">
                        {{ $newCount === 1 ? 'Ein Entwurf wartet auf Ihre Meinung' : $newCount.' Entwürfe warten auf Ihre Meinung' }}
                    </p>
                    <p class="text-base text-amber-900/80">Dauert nur ein paar Minuten.</p>
                </div>
            </div>
        @elseif ($previews->isNotEmpty())
            <div class="flex items-center gap-4 rounded-2xl bg-brand-soft px-5 py-4 ring-1 ring-brand/15" role="status">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand text-white">
                    <x-icon name="check" class="size-6" />
                </span>
                <div>
                    <p class="text-lg font-semibold text-brand-dark">Alles erledigt – vielen Dank!</p>
                    <p class="text-base text-brand-dark/80">Wir melden uns, sobald es Neues gibt.</p>
                </div>
            </div>
        @endif
    </section>

    @if ($previews->isNotEmpty())
        <h2 class="mt-12 text-xl font-semibold tracking-tight sm:mt-14">
            Ihre Entwürfe <span class="font-normal sg-muted">({{ $previews->count() }})</span>
        </h2>

        <div class="mt-5 space-y-6">
            @foreach ($previews as $preview)
                @include('portal._draft-card', ['preview' => $preview, 'project' => $preview->project])
            @endforeach
        </div>
    @endif

    @if ($withoutDraft->isNotEmpty())
        <h2 class="mt-12 text-xl font-semibold tracking-tight">
            {{ $previews->isEmpty() ? 'Ihre Projekte' : 'Weitere Projekte' }}
        </h2>

        <ul class="mt-5 divide-y divide-line overflow-hidden sg-card p-0">
            @foreach ($withoutDraft as $project)
                @php($done = in_array($project->status, [\App\Enums\ProjectStatus::Completed, \App\Enums\ProjectStatus::Archived], true))
                <li class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-4 sm:px-6">
                    <span class="flex items-center gap-3 text-lg font-semibold">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-paper text-ink-muted">
                            <x-icon name="folder" />
                        </span>
                        {{ $project->name }}
                    </span>
                    <span class="flex items-center gap-2 text-base sg-muted">
                        <x-icon :name="$done ? 'check' : 'clock'" class="size-5 {{ $done ? 'text-brand' : '' }}" />
                        {{ $done ? 'Abgeschlossen' : 'In Arbeit – wir schreiben Ihnen, sobald es etwas zu sehen gibt' }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($projects->isEmpty())
        <div class="mt-12 sg-card p-10 text-center">
            <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                <x-icon name="clock" class="size-7" />
            </span>
            <p class="mt-4 text-lg font-semibold">Gerade ist nichts für Sie freigegeben.</p>
            <p class="mt-1 text-base sg-muted">Wir schreiben Ihnen eine E-Mail, sobald es etwas zu sehen gibt.</p>
        </div>
    @endif

    <section class="mt-12 grid gap-4 sm:grid-cols-2">
        <div class="flex gap-4 rounded-3xl bg-white/60 p-6 ring-1 ring-ink/5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                <x-icon name="message" />
            </span>
            <div>
                <p class="text-lg font-semibold">Was passiert nach Ihrer Antwort?</p>
                <p class="mt-1 text-base sg-muted">
                    Wir sehen Ihre Rückmeldung sofort und melden uns per E-Mail, wenn wir etwas ändern.
                </p>
            </div>
        </div>
        <div class="flex gap-4 rounded-3xl bg-white/60 p-6 ring-1 ring-ink/5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                <x-icon name="mail" />
            </span>
            <div>
                <p class="text-lg font-semibold">Fragen?</p>
                <p class="mt-1 text-base sg-muted">
                    Antworten Sie einfach auf unsere E-Mail – wir helfen gern weiter.
                </p>
            </div>
        </div>
    </section>
@endsection
