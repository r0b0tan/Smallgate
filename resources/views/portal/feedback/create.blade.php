@extends('layouts.app')

@section('title', 'Änderung wünschen')

@section('content')
    <a href="{{ route('portal.dashboard') }}#entwurf-{{ $preview->id }}" class="inline-flex items-center gap-2 text-sm sg-link">
        <x-icon name="arrow-left" /> Zurück zur Übersicht
    </a>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="sg-card p-6 sm:p-8">
            <p class="sg-eyebrow">{{ $project->name }} · {{ $preview->name }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Was sollen wir ändern?</h1>
            <p class="mt-2 sg-muted">
                Schreiben Sie uns in eigenen Worten, was Ihnen auffällt. Ein paar Stichworte genügen.
            </p>

            <div class="mt-6"><x-errors /></div>

            <form method="POST" action="{{ route('portal.previews.feedback.store', [$project, $preview]) }}" class="space-y-6">
                @csrf
                <input type="hidden" name="decision" value="{{ \App\Enums\FeedbackDecision::ChangesRequested->value }}">
                <input type="hidden" name="version" value="{{ $preview->version }}">

                <div>
                    <label for="comment" class="sg-label">
                        Ihr Hinweis <span class="font-normal sg-muted">(freiwillig)</span>
                    </label>
                    <textarea id="comment" name="comment" rows="7" maxlength="2000" aria-describedby="comment-hint"
                              placeholder="z. B. „Das Foto oben bitte heller“ oder „Die Telefonnummer fehlt“"
                              class="sg-field mt-2">{{ old('comment') }}</textarea>
                    <p id="comment-hint" class="mt-2 text-sm sg-muted">
                        Sie können das Feld auch leer lassen – dann melden wir uns bei Ihnen.
                    </p>
                    @error('comment')
                        <p class="mt-1 text-sm sg-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('portal.dashboard') }}#entwurf-{{ $preview->id }}"
                       class="sg-btn-secondary min-h-12 text-base">
                        Abbrechen
                    </a>
                    <button type="submit" class="sg-btn-primary min-h-12 text-base sm:order-first">
                        <x-icon name="message" />
                        Änderungswunsch senden
                    </button>
                </div>
            </form>
        </div>

        {{-- Which draft this is about, so nobody has to remember. --}}
        <aside class="sg-card overflow-hidden p-0">
            <div class="aspect-[16/9] border-b border-line bg-paper">
                @if ($preview->hasCurrentThumbnail())
                    <img src="{{ route('portal.previews.thumbnail', [$project, $preview, 'v' => $preview->thumbnail_generated_at?->timestamp]) }}"
                         alt="Vorschaubild: {{ $preview->name }}" class="size-full object-cover object-top">
                @else
                    <div class="flex size-full items-center justify-center text-ink-muted">
                        <x-icon name="browser" class="size-10" />
                    </div>
                @endif
            </div>
            <div class="p-5">
                <p class="font-semibold">{{ $preview->name }}</p>
                <a href="{{ route('portal.previews.show', [$project, $preview]) }}" target="_blank" rel="noopener noreferrer"
                   class="mt-2 inline-flex items-center gap-2 text-sm sg-link">
                    Noch einmal ansehen <x-icon name="external" class="size-4" />
                    <span class="sr-only">(öffnet sich in einem neuen Fenster)</span>
                </a>
            </div>
        </aside>
    </div>
@endsection
