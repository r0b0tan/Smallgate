<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\DirectoryStatus;
use App\Enums\PreviewUploadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ExtractPreviewUpload;
use App\Models\Activity;
use App\Models\Preview;
use App\Models\PreviewUpload;
use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Entwurf hochladen" (ADR 0004). The request only stores the ZIP under a
 * random name and queues the job; unpacking happens in the queue worker, and
 * the project page shows the progress until it is done. The archive's own
 * name is never used as a path, never stored and never logged.
 */
class PreviewUploadController extends Controller
{
    public function store(Request $request, Project $project, Preview $preview): RedirectResponse
    {
        $this->authorize('upload', $preview);
        abort_unless($preview->project_id === $project->id, 404);

        $back = redirect()->route('admin.projects.show', $project);

        // One error bag per preview: the project page lists several forms.
        $request->validateWithBag('upload_'.$preview->id, [
            'archiv' => [
                'required', 'file',
                'max:'.intdiv((int) config('previews.uploads.max_zip_bytes'), 1024),
                // Taken from the file's content, not from its name.
                'mimes:zip', 'mimetypes:application/zip',
            ],
        ], [
            'archiv.required' => 'Bitte wählen Sie ein ZIP-Archiv aus.',
            'archiv.mimes' => 'Der Entwurf muss ein ZIP-Archiv sein.',
            'archiv.mimetypes' => 'Der Entwurf muss ein ZIP-Archiv sein.',
            'archiv.max' => 'Das ZIP-Archiv ist zu groß.',
            'archiv.uploaded' => 'Das ZIP-Archiv konnte nicht hochgeladen werden. Ist es zu groß?',
        ]);

        if ($project->directory_status !== DirectoryStatus::Created) {
            return $back->with('error', 'Bitte legen Sie zuerst den Projektordner an.');
        }

        $busy = 'Für diese Vorschau wird gerade ein Entwurf entpackt.';

        if ($preview->uploads()->whereIn('status', [PreviewUploadStatus::Pending, PreviewUploadStatus::Extracting])->exists()) {
            return $back->with('error', $busy);
        }

        $upload = new PreviewUpload;
        $upload->preview_id = $preview->id;
        $upload->user_id = $request->user()->id;
        $upload->status = PreviewUploadStatus::Pending;
        $upload->created_at = Carbon::now();

        // The check above is for the usual case; preview_uploads_one_running
        // catches two forms sent at the same moment. In a transaction of its
        // own, so the violation does not take any surrounding one down.
        try {
            DB::transaction(fn () => $upload->save());
        } catch (UniqueConstraintViolationException) {
            return $back->with('error', $busy);
        }

        $stored = $request->file('archiv')->storeAs(
            config('previews.uploads.directory'),
            basename($upload->archivePath()),
            config('previews.uploads.disk'),
        );

        if ($stored === false) {
            $upload->delete();

            return $back->with('error', 'Das ZIP-Archiv konnte nicht gespeichert werden.');
        }

        Activity::record(ActivityAction::PreviewUploadStarted, $preview, properties: [
            'bytes' => $request->file('archiv')->getSize(),
        ]);

        ExtractPreviewUpload::dispatch($upload->id, $request->user()->id);

        return $back->with('status', 'Der Entwurf wird entpackt.');
    }
}
