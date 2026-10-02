@use('App\Enums\FeedbackDecision')

{{-- The last three answers from anybody at this customer: proof that a click
     arrived. The full list is the archive behind "Alle". --}}
@php
    $showProject = $projects->count() > 1;
@endphp

<aside class="sg-card p-0" aria-labelledby="letzte-rueckmeldungen">
    <div class="mx-5 flex items-baseline justify-between gap-3 border-b border-line py-4">
        <h2 id="letzte-rueckmeldungen" class="text-lg font-semibold">Letzte Rückmeldungen</h2>
        @if ($recentFeedback->isNotEmpty())
            <a href="{{ route('portal.feedback.index') }}" class="text-sm sg-link">Alle</a>
        @endif
    </div>

    @if ($recentFeedback->isEmpty())
        <p class="px-5 py-6 text-sm sg-muted">
            Noch keine Rückmeldungen. Sobald Sie einen Entwurf freigeben oder eine Änderung wünschen, steht es hier.
        </p>
    @else
        <ul class="mx-5 divide-y divide-line">
            @foreach ($recentFeedback as $feedback)
                @php
                    $approved = $feedback->decision === FeedbackDecision::Approved;
                    $onPage = in_array($feedback->preview_id, $offeredIds, true);
                @endphp
                <li class="flex gap-4 py-4">
                    <x-icon name="file" class="mt-0.5 size-6 text-ink-muted" />
                    <div class="min-w-0">
                        <p class="text-sm font-medium">
                            <time datetime="{{ $feedback->created_at->toIso8601String() }}">{{ $feedback->created_at->copy()->timezone($tz)->format('d.m.y') }}</time>
                            –
                            @if ($onPage)
                                <a href="#entwurf-{{ $feedback->preview_id }}" class="hover:underline">{{ $feedback->preview->name }}</a>
                            @else
                                {{ $feedback->preview->name }}
                            @endif
                        </p>
                        @if ($showProject)
                            <p class="truncate text-xs sg-muted">{{ $feedback->preview->project->name }}</p>
                        @endif
                        <p @class(['mt-1.5 flex items-center gap-2 text-sm', 'text-brand' => $approved, 'text-amber-800' => ! $approved])>
                            <x-icon :name="$approved ? 'check-circle' : 'edit-circle'" class="size-5" />
                            <span class="text-ink">{{ $approved ? 'Passt so!' : 'Änderung gewünscht' }}</span>
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</aside>
