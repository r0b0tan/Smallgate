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
 */
class PreviewController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('managePreviews', $project);

        return view('admin.previews.index', [
            'project' => $project->load('customer'),
            'previews' => $project->previews()->orderBy('name')->get(),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('managePreviews', $project);

        return view('admin.previews.create', [
            'project' => $project,
            'preview' => new Preview([
                'status' => PreviewStatus::Draft->value,
                'target_type' => PreviewTargetType::StaticDirectory->value,
            ]),
            'statuses' => PreviewStatus::options(),
            'targetTypes' => PreviewTargetType::options(),
        ]);
    }

    public function store(StorePreviewRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('managePreviews', $project);

        $preview = new Preview;
        $preview->fill($request->validated());
        // Explicit, never mass assigned: this is what scopes visibility.
        $preview->project_id = $project->id;
        $preview->save();

        return redirect()->route('admin.projects.previews.index', $project)
            ->with('status', 'Vorschau wurde angelegt.');
    }

    public function edit(Project $project, Preview $preview): View
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        return view('admin.previews.edit', [
            'project' => $project,
            'preview' => $preview,
            'statuses' => PreviewStatus::options(),
            'targetTypes' => PreviewTargetType::options(),
        ]);
    }

    public function update(UpdatePreviewRequest $request, Project $project, Preview $preview): RedirectResponse
    {
        $this->authorize('managePreviews', $project);
        $this->ensureBelongsToProject($project, $preview);

        $preview->fill($request->validated());
        $preview->save();

        return redirect()->route('admin.projects.previews.index', $project)
            ->with('status', 'Vorschau wurde gespeichert.');
    }

    public function destroy(Project $project, Preview $preview): RedirectResponse
    {
        $this->authorize('delete', $preview);
        $this->ensureBelongsToProject($project, $preview);

        $preview->delete();

        return redirect()->route('admin.projects.previews.index', $project)
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

        $preview->status = $result->status;
        $preview->provisioned_at = $result->successful ? Carbon::now() : null;
        $preview->save();

        return redirect()->route('admin.projects.previews.index', $project)
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
