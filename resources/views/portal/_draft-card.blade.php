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

<article id="entwurf-{{ $preview->id }}" aria-labelledby="titel-{{ $preview->id }}" class="scroll-mt-24 sg-card p-5 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="sg-eyebrow">{{ $current ? 'Aktueller Entwurf' : 'Entwurf' }}</p>
            <h2 id="titel-{{ $preview->id }}" class="mt-1 text-xl font-semibold tracking-tight">{{ $preview->name }}</h2>
            <p class="mt-1 text-sm sg-muted">
                Zuletzt aktualisiert: {{ $preview->lastUpdatedAt()->copy()->timezone($tz)->format('d.m.Y, H:i') }} Uhr
            </p>
            {{-- Where the click will land, so nobody is surprised by the address bar. --}}
            @if ($address = $preview->displayAddress())
                <p class="mt-0.5 truncate text-xs sg-muted">{{ $address }}</p>
            @endif
        </div>

        @if ($feedback === null)
            <span class="sg-badge bg-amber-50 text-amber-900 ring-amber-200">
                <span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                Wartet auf Ihre Meinung
            </span>
        @elseif ($feedback->decision === FeedbackDecision::Approved)
            <span class="sg-badge bg-emerald-50 text-emerald-800 ring-emerald-200">
                <x-icon name="check" class="size-3.5" /> Freigegeben
            </span>
        @else
            <span class="sg-badge bg-sky-50 text-sky-900 ring-sky-200">
                <x-icon name="pencil" class="size-3.5" /> Änderung gewünscht
            </span>
        @endif
    </div>

    {{-- The picture is the way in: clicking what you see is the first thing
         most people try, and the label says that it works. --}}
    <a href="{{ $openUrl }}" target="_blank" rel="noopener noreferrer"
       class="group relative mt-5 block aspect-[4/3] overflow-hidden sm:aspect-[16/9] rounded-md bg-paper ring-1 ring-inset ring-line">
        @if ($preview->hasCurrentThumbnail())
            <img src="{{ route('portal.previews.thumbnail', [$project, $preview, 'v' => $preview->thumbnail_generated_at?->timestamp]) }}"
                 alt="Vorschaubild: {{ $preview->name }}" loading="lazy" width="1280" height="720"
                 class="size-full object-cover object-top transition duration-500 group-hover:scale-[1.015]">
        @else
            {{-- No picture (yet): say what it is, so the card still works. --}}
            <span class="flex size-full flex-col items-center justify-center gap-2 p-6 pb-16 text-center">
                <x-icon name="browser" class="size-9 text-ink-muted" />
                <span class="font-semibold">Website-Entwurf</span>
                <span class="text-sm sg-muted">
                    {{ $preview->thumbnail_status === ThumbnailStatus::Pending ? 'Das Vorschaubild wird gerade erstellt.' : $preview->name }}
                </span>
            </span>
        @endif

        <span class="absolute bottom-3 right-3 inline-flex sm:bottom-4 sm:right-4 items-center gap-2 rounded-md bg-white/95 px-3.5 py-2 text-sm
                     font-semibold text-ink shadow-lift ring-1 ring-line transition group-hover:bg-white">
            <x-icon name="eye" class="size-4" />
            Entwurf ansehen
            <x-icon name="external" class="size-4 text-ink-muted" />
            <span class="sr-only">(öffnet sich in einem neuen Fenster)</span>
        </span>
    </a>

    @can('create', [PreviewFeedback::class, $preview])
        @if ($feedback === null)
            {{-- One form, two submit buttons: whichever is pressed is the answer,
                 the note goes with either. --}}
            <form method="POST" action="{{ route('portal.previews.feedback.store', [$project, $preview]) }}" class="mt-5">
                @csrf
                <input type="hidden" name="version" value="{{ $preview->version }}">

                <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                    <button type="submit" name="decision" value="{{ FeedbackDecision::Approved->value }}"
                            class="sg-btn-primary min-h-12 text-base">
                        <x-icon name="check" /> Passt so (Freigeben)
                    </button>
                    <button type="submit" name="decision" value="{{ FeedbackDecision::ChangesRequested->value }}"
                            class="sg-btn-quiet min-h-12 text-base">
                        <x-icon name="x" /> Änderung wünschen
                    </button>
                </div>

                <label for="anmerkung-{{ $preview->id }}" class="sr-only">Anmerkungen (freiwillig)</label>
                <div class="relative mt-4">
                    <x-icon name="message" class="pointer-events-none absolute left-3.5 top-3.5 text-ink-muted" />
                    <textarea id="anmerkung-{{ $preview->id }}" name="comment" rows="2" maxlength="2000"
                              placeholder="Optional: Anmerkungen …"
                              class="sg-field min-h-12 resize-y pl-11"></textarea>
                </div>
            </form>
        @else
            {{-- The confirmation lives where the buttons were, so it is seen
                 without looking for it. --}}
            <div @class([
                    'mt-5 rounded-md bg-brand-tint p-4 ring-inset',
                    'ring-1 ring-line' => ! $justSent,
                    'ring-2 ring-brand/40' => $justSent,
                 ])
                 @if ($justSent) role="status" @endif>
                <p class="font-semibold">
                    {{ $justSent ? 'Vielen Dank!' : '' }}
                    @if ($feedback->decision === FeedbackDecision::Approved)
                        Sie haben den Entwurf am {{ $date($feedback->created_at) }} freigegeben.
                    @else
                        Ihr Änderungswunsch vom {{ $date($feedback->created_at) }} ist bei uns angekommen.
                        Wir melden uns per E-Mail.
                    @endif
                </p>
                @if ($feedback->comment)
                    <blockquote class="mt-3 whitespace-pre-line border-l-4 border-brand/25 pl-3 italic sg-muted">{{ $feedback->comment }}</blockquote>
                @endif
                <a href="{{ route('portal.previews.feedback.create', [$project, $preview]) }}"
                   class="mt-3 inline-flex items-center gap-2 text-sm sg-link">
                    <x-icon name="pencil" class="size-4" />
                    {{ $feedback->decision === FeedbackDecision::Approved ? 'Doch etwas ändern?' : 'Noch etwas ergänzen' }}
                </a>
            </div>
        @endif
    @endcan
</article>
