@use('App\Enums\FeedbackDecision')
@use('App\Enums\ThumbnailStatus')
@use('App\Models\PreviewFeedback')

@php
    $tz = config('smallgate.display_timezone');
    $date = fn ($moment) => $moment->copy()->timezone($tz)->locale('de')->isoFormat('D. MMMM YYYY');

    $feedback = $preview->currentFeedback();
    $justSent = session('feedback_sent') === $preview->id;
    // Through the portal route, never straight to the address: access and
    // status are checked again at the moment of the click.
    $openUrl = route('portal.previews.show', [$project, $preview]);
@endphp

<article id="entwurf-{{ $preview->id }}" aria-labelledby="titel-{{ $preview->id }}"
         class="scroll-mt-24 sg-card p-4 sm:p-6 lg:p-8">
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-10">

        {{-- The picture is a link to the draft too: clicking what you see is
             the first thing most people try. --}}
        <a href="{{ $openUrl }}" target="_blank" rel="noopener noreferrer"
           class="group relative block self-start overflow-hidden rounded-2xl bg-paper ring-1 ring-ink/10 transition
                  hover:shadow-lift">
            <div class="flex items-center gap-2 border-b border-ink/5 bg-white px-3 py-2.5" aria-hidden="true">
                <span class="size-2.5 rounded-full bg-[#ff5f57]"></span>
                <span class="size-2.5 rounded-full bg-[#febc2e]"></span>
                <span class="size-2.5 rounded-full bg-[#28c840]"></span>
                <span class="ml-2 min-w-0 flex-1 truncate rounded-md bg-paper px-3 py-1 text-center text-xs sg-muted">
                    {{ $preview->displayAddress() }}
                </span>
            </div>

            <div class="relative aspect-[16/10] overflow-hidden">
                @if ($preview->hasCurrentThumbnail())
                    <img src="{{ route('portal.previews.thumbnail', [$project, $preview, 'v' => $preview->thumbnail_generated_at?->timestamp]) }}"
                         alt="Vorschaubild: {{ $preview->name }}" loading="lazy" width="720" height="450"
                         class="size-full object-cover object-top transition duration-500 group-hover:scale-[1.02]">
                @else
                    {{-- No picture (yet): say what it is, so the card still works. --}}
                    <div class="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-br
                                from-brand-soft to-brand-tint p-6 text-center">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-white text-brand shadow-card">
                            <x-icon name="browser" class="size-7" />
                        </span>
                        <span class="text-base font-semibold text-brand-dark">Website-Entwurf</span>
                        <span class="text-sm sg-muted">
                            {{ $preview->thumbnail_status === ThumbnailStatus::Pending ? 'Das Vorschaubild wird gerade erstellt.' : $preview->name }}
                        </span>
                    </div>
                @endif

                <span class="absolute inset-0 flex items-end justify-center bg-gradient-to-t from-ink/50 via-transparent
                             p-5 opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
                      aria-hidden="true">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-base font-semibold text-ink shadow">
                        <x-icon name="eye" /> Ansehen
                    </span>
                </span>
            </div>
            <span class="sr-only">Entwurf „{{ $preview->name }}“ ansehen (öffnet sich in einem neuen Fenster)</span>
        </a>

        <div class="flex flex-col">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="sg-eyebrow">{{ $project->name }}</p>

                @if ($feedback === null)
                    <span class="sg-chip bg-amber-50 text-amber-900 ring-amber-200">
                        <span class="size-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                        Wartet auf Ihre Meinung
                    </span>
                @elseif ($feedback->decision === FeedbackDecision::Approved)
                    <span class="sg-chip bg-brand-soft text-brand-dark ring-brand/20">
                        <x-icon name="check" class="size-4" /> Freigegeben
                    </span>
                @else
                    <span class="sg-chip bg-sky-50 text-sky-900 ring-sky-200">
                        <x-icon name="pencil" class="size-4" /> Änderung gewünscht
                    </span>
                @endif
            </div>

            <h3 id="titel-{{ $preview->id }}" class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ $preview->name }}
            </h3>
            <p class="mt-2 flex items-center gap-2 text-base sg-muted">
                <x-icon name="calendar" class="size-4" />
                Aktualisiert am {{ $date($preview->lastUpdatedAt()) }}
            </p>

            <ol class="mt-6 space-y-6">
                {{-- Step 1: look at it. --}}
                <li class="flex gap-4">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-base font-bold text-white"
                          aria-hidden="true">1</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-semibold">Entwurf ansehen</p>
                        <a href="{{ $openUrl }}" target="_blank" rel="noopener noreferrer" class="sg-btn-cta mt-3">
                            <x-icon name="eye" class="size-6" />
                            Entwurf ansehen
                            <x-icon name="external" class="size-5 opacity-80" />
                            <span class="sr-only">(öffnet sich in einem neuen Fenster)</span>
                        </a>
                        <p class="mt-3 flex items-start gap-2 text-base sg-muted">
                            <x-icon name="shield" class="mt-0.5 text-brand" />
                            Öffnet sich in einem neuen Fenster. Sie können dabei nichts verändern oder kaputt machen.
                        </p>
                    </div>
                </li>

                {{-- Step 2: say what you think. --}}
                @can('create', [PreviewFeedback::class, $preview])
                    <li class="flex gap-4">
                        @if ($feedback === null)
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-base
                                         font-bold text-brand-dark ring-1 ring-brand/20" aria-hidden="true">2</span>
                        @else
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-white"
                                  aria-hidden="true"><x-icon name="check" /></span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-semibold">Ihre Meinung</p>

                            @if ($feedback === null)
                                <p class="mt-1 text-base sg-muted">Gefällt Ihnen der Entwurf?</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                    <form method="POST" action="{{ route('portal.previews.feedback.store', [$project, $preview]) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="{{ FeedbackDecision::Approved->value }}">
                                        <input type="hidden" name="version" value="{{ $preview->version }}">
                                        <button type="submit" class="sg-choice">
                                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
                                                <x-icon name="check" class="size-6" />
                                            </span>
                                            <span>
                                                <span class="block text-lg font-semibold">Passt so</span>
                                                <span class="block text-sm sg-muted">Entwurf freigeben</span>
                                            </span>
                                        </button>
                                    </form>
                                    <a href="{{ route('portal.previews.feedback.create', [$project, $preview]) }}" class="sg-choice">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                                            <x-icon name="pencil" class="size-6" />
                                        </span>
                                        <span>
                                            <span class="block text-lg font-semibold">Änderung wünschen</span>
                                            <span class="block text-sm sg-muted">Kurz sagen, was anders soll</span>
                                        </span>
                                    </a>
                                </div>
                            @else
                                {{-- The confirmation lives where the buttons were, so
                                     it is seen without looking for it. --}}
                                <div @class([
                                        'mt-3 rounded-2xl bg-brand-tint p-4',
                                        'ring-1 ring-brand/15' => ! $justSent,
                                        'ring-2 ring-brand/50' => $justSent,
                                     ])
                                     @if ($justSent) role="status" @endif>
                                    <p class="text-base font-semibold text-brand-dark">
                                        {{ $justSent ? 'Vielen Dank!' : '' }}
                                        @if ($feedback->decision === FeedbackDecision::Approved)
                                            Sie haben den Entwurf am {{ $date($feedback->created_at) }} freigegeben.
                                        @else
                                            Ihr Änderungswunsch vom {{ $date($feedback->created_at) }} ist bei uns angekommen.
                                            Wir melden uns per E-Mail.
                                        @endif
                                    </p>
                                    @if ($feedback->comment)
                                        <blockquote class="mt-3 whitespace-pre-line border-l-4 border-brand/30 pl-3 text-base italic sg-muted">{{ $feedback->comment }}</blockquote>
                                    @endif
                                    <a href="{{ route('portal.previews.feedback.create', [$project, $preview]) }}"
                                       class="mt-3 inline-flex items-center gap-2 text-base sg-link">
                                        <x-icon name="pencil" class="size-4" />
                                        {{ $feedback->decision === FeedbackDecision::Approved ? 'Doch etwas ändern?' : 'Noch etwas ergänzen' }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </li>
                @endcan
            </ol>
        </div>
    </div>
</article>
