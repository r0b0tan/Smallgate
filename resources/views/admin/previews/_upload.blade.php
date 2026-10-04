{{-- Uploading a draft as a ZIP (ADR 0004). While the queue worker unpacks it
     -- or is still creating the project folder the form waits for -- app.js
     refreshes this block from a fresh copy of the page until data-poll
     disappears, as for the project folder, and then reloads the page: the
     card's version, target and thumbnail have changed as well. --}}
@php($upload = $preview->latestUpload)
@php($running = $upload?->status->isRunning() ?? false)
@php($folderPending = $project->directory_status === \App\Enums\DirectoryStatus::Pending)
@php($timezone = config('smallgate.display_timezone'))
<div id="preview-upload-{{ $preview->id }}" aria-live="polite" class="mt-4 border-t border-line pt-4"
     @if ($running || $folderPending) data-poll data-poll-reload @endif>
    <h4 class="text-sm font-semibold">Entwurf hochladen</h4>

    @if ($running)
        <p class="mt-2 flex items-center gap-2 text-sm text-ink">
            <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-line border-t-brand
                         motion-reduce:animate-none" aria-hidden="true"></span>
            Entwurf wird entpackt …
        </p>
        <div class="mt-2 h-1 overflow-hidden rounded-full bg-line" aria-hidden="true">
            <div class="sg-progress-indeterminate h-full w-1/3 rounded-full bg-brand"></div>
        </div>

        {{-- Shown by app.js once it stops waiting: the worker may not be running. --}}
        <p data-poll-stalled class="mt-2 text-xs sg-muted" hidden>
            Dauert ungewöhnlich lange. Läuft der Queue-Worker?
        </p>
        <noscript>
            <a href="{{ route('admin.projects.show', $project) }}" class="sg-link text-xs">Stand aktualisieren</a>
        </noscript>
    @else
        @if ($upload?->status === \App\Enums\PreviewUploadStatus::Failed)
            <p class="mt-2 sg-alert-error">{{ $upload->error }}</p>
        @elseif ($upload?->status === \App\Enums\PreviewUploadStatus::Ready)
            <p class="mt-2 text-xs sg-faint">
                Zuletzt hochgeladen am {{ $upload->finished_at->timezone($timezone)->format('d.m.Y H:i') }}:
                {{ number_format($upload->file_count, 0, ',', '.') }} {{ $upload->file_count === 1 ? 'Datei' : 'Dateien' }},
                {{ number_format($upload->size_bytes / 1048576, 1, ',', '.') }} MB.
            </p>
        @endif

        @if ($folderPending)
            <p class="mt-2 text-sm sg-muted">Der Projektordner wird gerade angelegt. Danach erscheint hier das Formular.</p>
        @elseif ($project->directory_status !== \App\Enums\DirectoryStatus::Created)
            <p class="mt-2 text-sm sg-muted">Zum Hochladen zuerst oben den Projektordner anlegen.</p>
        @else
            <form method="POST" enctype="multipart/form-data"
                  action="{{ route('admin.projects.previews.upload', [$project, $preview]) }}"
                  class="mt-2 flex flex-wrap items-center gap-2">
                @csrf
                <label for="archiv-{{ $preview->id }}" class="sr-only">ZIP-Archiv für {{ $preview->name }}</label>
                <input id="archiv-{{ $preview->id }}" name="archiv" type="file" required
                       accept=".zip,application/zip"
                       class="block min-w-0 flex-1 text-sm text-ink-muted
                              file:mr-4 file:min-h-10 file:cursor-pointer file:rounded-md file:border-0
                              file:bg-brand-soft file:px-4 file:text-sm file:font-semibold file:text-brand
                              hover:file:bg-brand-tint">
                <button type="submit" class="sg-btn-secondary">
                    <x-icon name="folder" class="size-4" />
                    Hochladen
                </button>
            </form>
            @error('archiv', 'upload_'.$preview->id)
                <p class="mt-1 text-sm sg-error">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs sg-faint">
                Ein ZIP-Archiv mit einer index.html, höchstens
                {{ intdiv((int) config('previews.uploads.max_zip_bytes'), 1048576) }} MB.
                @if ($preview->status === \App\Enums\PreviewStatus::Available)
                    Die Vorschau ist freigegeben: Der Kunde sieht den neuen Stand sofort als neue Version.
                @else
                    Die Vorschau zeigt danach den neuen Stand. Für den Kunden freigeben wie gewohnt mit „Bereitstellen“.
                @endif
            </p>
        @endif
    @endif
</div>
