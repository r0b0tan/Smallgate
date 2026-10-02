<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\PreviewProvisioner;
use App\Enums\ActivityAction;
use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Enums\ThumbnailStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePreviewRequest;
use App\Http\Requests\Admin\UpdatePreviewRequest;
use App\Jobs\GeneratePreviewThumbnail;
use App\Models\Activity;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Previews are always addressed through their project, so a preview of another
 * project can never be reached through this controller by id alone.
 *
 * There is no index: the project page lists the previews and carries every
 * action, so an administrator never has to work out which of two lists is the
 * real one.
 */
class PreviewController extends Controller
{
    public function create(Project $project): View
    {
        $this->authorize('managePreviews', $project);

        return view('admin.previews.create', [
            'project' => $project,
            'preview' => new Preview([
                'target_type' => (PreviewTargetType::enabled()[0] ?? null)?->value,
            ]),
            'targetTypes' => PreviewTargetType::options(),
        ]);
    }

    public function store(StorePreviewRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('managePreviews', $project);

        $preview = new Preview;
        $preview->fill($request->validated());
        // Explicit, never mass assigned: the first scopes visibility, the second
        // decides whether the customer is offered the preview at all. A new
        // preview always starts as a draft and is released by provisioning it.
        $preview->project_id = $project->id;
        $preview->status = PreviewStatus::Draft;
        $preview->save();

        Activity::record(ActivityAction::PreviewCreated, $preview);

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Vorschau wurde als Entwurf angelegt. Zum Freigeben bereitstellen.');
    }

    public function edit(Project $project, Preview $preview): View
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        return view('admin.previews.edit', [
            'project' => $project,
            'preview' => $preview,
            'targetTypes' => PreviewTargetType::options(),
        ]);
    }

    public function update(UpdatePreviewRequest $request, Project $project, Preview $preview): RedirectResponse
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        $preview->fill($request->validated());
        $preview->save();

        if ($preview->wasChanged()) {
            Activity::record(ActivityAction::PreviewUpdated, $preview);
        }

        // Only claim there is something to re-provision when the save actually
        // changed a column -- an unchanged save leaves updated_at alone, so the
        // drift hint on the project page would not appear either.
        $message = $preview->wasChanged() && $preview->status === PreviewStatus::Available
            ? 'Vorschau wurde gespeichert. Zum Übernehmen erneut bereitstellen.'
            : 'Vorschau wurde gespeichert.';

        return redirect()->route('admin.projects.show', $project)->with('status', $message);
    }

    public function destroy(Project $project, Preview $preview): RedirectResponse
    {
        $this->authorize('delete', $preview);
        $this->ensureBelongsToProject($project, $preview);

        $preview->delete();

        // Recorded after the delete: the entry keeps the preview's name.
        Activity::record(ActivityAction::PreviewDeleted, $preview);

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Vorschau wurde gelöscht.');
    }

    /**
     * Hand the preview to the configured provisioner.
     *
     * In the MVP that is NullPreviewProvisioner, which changes nothing on the
     * server. The flow exists so the UI, the status transitions and the target
     * allowlist are already exercised before real provisioning lands.
     */
    public function provision(Project $project, Preview $preview, PreviewProvisioner $provisioner): RedirectResponse
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        $result = $provisioner->provision($preview);
        $now = Carbon::now();

        $preview->status = $result->status;
        $preview->provisioned_at = $result->successful ? $now : null;

        if ($result->successful) {
            // Pin the two timestamps to the same moment. A later updated_at is
            // what tells the administrator that the stored configuration has
            // drifted from what was last provisioned.
            $preview->updated_at = $now;

            // What the customer sees now is a new version: it asks for fresh
            // feedback and gets its own thumbnail.
            $preview->publishNewVersion();
        }

        $preview->save();

        if (! $result->successful) {
            Activity::record(ActivityAction::PreviewProvisionFailed, $preview);

            return redirect()->route('admin.projects.show', $project)->with('error', $result->message);
        }

        Activity::record(ActivityAction::PreviewProvisioned, $preview, properties: ['version' => $preview->version]);

        GeneratePreviewThumbnail::for($preview);

        return redirect()->route('admin.projects.show', $project)
            ->with('status', $result->message.' Version '.$preview->version.' ist für den Kunden sichtbar.');
    }

    /**
     * Queue a new screenshot of the current version, e.g. after a failure or
     * when files changed without a new provisioning.
     */
    public function regenerateThumbnail(Project $project, Preview $preview): RedirectResponse
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        if (! config('previews.thumbnails.enabled')) {
            return redirect()->route('admin.projects.show', $project)
                ->with('error', 'Vorschaubilder sind in der Konfiguration abgeschaltet.');
        }

        if (! $preview->status->isVisitable() || $preview->version < 1) {
            return redirect()->route('admin.projects.show', $project)
                ->with('error', 'Ein Vorschaubild wird erst nach der Bereitstellung erstellt.');
        }

        // Through the query builder: thumbnail state must not move updated_at,
        // which would wrongly report a configuration change.
        Preview::query()->whereKey($preview->id)->toBase()
            ->update(['thumbnail_status' => ThumbnailStatus::Pending->value]);

        GeneratePreviewThumbnail::for($preview);

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Das Vorschaubild wird im Hintergrund neu erstellt.');
    }

    /**
     * The stored thumbnail, current or stale. Administrators see a stale one
     * too, marked as such on the project page; customers never do.
     */
    public function thumbnail(Project $project, Preview $preview): StreamedResponse
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        $disk = Storage::disk(config('previews.thumbnails.disk'));

        abort_if($preview->thumbnail_path === null || ! $disk->exists($preview->thumbnail_path), 404);

        return $disk->response($preview->thumbnail_path, 'vorschau.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Take a preview off the portal again. The customer immediately stops being
     * offered it; nothing on the server is touched.
     */
    public function disable(Project $project, Preview $preview, PreviewProvisioner $provisioner): RedirectResponse
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        $result = $provisioner->deprovision($preview);

        $preview->status = $result->status;
        $preview->save();

        if ($result->successful) {
            Activity::record(ActivityAction::PreviewDisabled, $preview);
        }

        return redirect()->route('admin.projects.show', $project)
            ->with($result->successful ? 'status' : 'error', $result->message);
    }

    /**
     * Nested resources must be genuinely nested: a preview id that belongs to
     * a different project is treated as not found, not as forbidden.
     */
    private function ensureBelongsToProject(Project $project, Preview $preview): void
    {
        abort_unless($preview->project_id === $project->id, 404);
    }
}
