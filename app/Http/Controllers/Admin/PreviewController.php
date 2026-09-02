<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\PreviewProvisioner;
use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePreviewRequest;
use App\Http\Requests\Admin\UpdatePreviewRequest;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

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
                'target_type' => PreviewTargetType::StaticDirectory->value,
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
        }

        $preview->save();

        return redirect()->route('admin.projects.show', $project)
            ->with($result->successful ? 'status' : 'error', $result->message);
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
