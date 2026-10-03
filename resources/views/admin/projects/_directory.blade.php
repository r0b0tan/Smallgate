{{-- The project folder. While the queue worker creates it, app.js refreshes
     this block from a fresh copy of the page until data-poll disappears; the
     element itself stays, so screen readers announce the change. --}}
@php($status = $project->directory_status)
<div id="project-directory" aria-live="polite"
     @if ($status === \App\Enums\DirectoryStatus::Pending) data-poll @endif>
    <dt class="sg-muted">Ordner</dt>
    <dd class="mt-1 space-y-2">
        @if ($status === \App\Enums\DirectoryStatus::Created)
            <p class="break-all font-mono text-xs text-ink">{{ $project->directoryPath() }}</p>
            <p class="text-xs sg-faint">
                Angelegt am {{ $project->directory_created_at->timezone(config('smallgate.display_timezone'))->format('d.m.Y H:i') }}.
            </p>
        @elseif ($status === \App\Enums\DirectoryStatus::Pending)
            <p class="flex items-center gap-2 text-ink">
                <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-line border-t-brand
                             motion-reduce:animate-none" aria-hidden="true"></span>
                Ordner wird angelegt …
            </p>
            <div class="h-1 overflow-hidden rounded-full bg-line" aria-hidden="true">
                <div class="sg-progress-indeterminate h-full w-1/3 rounded-full bg-brand"></div>
            </div>
            <p class="break-all font-mono text-xs sg-faint">{{ $project->directoryPath() }}</p>

            {{-- Shown by app.js once it stops waiting: the worker may not be
                 running. Without JavaScript the link below reloads instead. --}}
            <div data-poll-stalled class="space-y-2" hidden>
                <p class="text-xs sg-muted">
                    Dauert ungewöhnlich lange. Läuft der Queue-Worker?
                </p>
                <form method="POST" action="{{ route('admin.projects.directory.store', $project) }}">
                    @csrf
                    <button type="submit" class="sg-btn-secondary">Erneut anstoßen</button>
                </form>
            </div>
            <noscript>
                <a href="{{ route('admin.projects.show', $project) }}" class="sg-link text-xs">Stand aktualisieren</a>
            </noscript>
        @else
            @if ($status === \App\Enums\DirectoryStatus::Failed)
                <p class="sg-alert-error">{{ $project->directory_error }}</p>
            @else
                <p class="text-xs sg-faint">Noch kein Ordner angelegt.</p>
            @endif
            <form method="POST" action="{{ route('admin.projects.directory.store', $project) }}">
                @csrf
                <button type="submit" class="sg-btn-secondary">
                    <x-icon name="folder" class="size-4" />
                    {{ $status === \App\Enums\DirectoryStatus::Failed ? 'Erneut versuchen' : 'Ordner anlegen' }}
                </button>
            </form>
            <p class="break-all font-mono text-xs sg-faint">
                {{ rtrim(config('smallgate.project_directories.root'), '/') }}/{{ $project->directory ?? $project->proposedDirectory() }}
            </p>
        @endif
    </dd>
</div>
