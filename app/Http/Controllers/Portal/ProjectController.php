<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's read-only view of their own projects.
 *
 * Projects are resolved through the visibility scope by id rather than through
 * route model binding, so a foreign or unknown id produces an identical 404.
 * A "403 Forbidden" would confirm that the id exists and belongs to someone
 * else, which is exactly the leak the spec asks to avoid.
 */
class ProjectController extends Controller
{
    public function show(Request $request, string $project): View
    {
        $model = Project::query()
            ->visibleTo($request->user())
            ->with(['customer', 'previews' => fn ($q) => $q->orderBy('name')])
            ->whereKey($project)
            ->firstOrFail();

        // Belt and braces: the policy has to agree with the scope.
        $this->authorize('view', $model);

        return view('portal.projects.show', [
            'project' => $model,
            'previews' => $model->previews,
        ]);
    }

    public function showPreview(Request $request, string $project, string $preview): View
    {
        $projectModel = Project::query()
            ->visibleTo($request->user())
            ->whereKey($project)
            ->firstOrFail();

        $this->authorize('view', $projectModel);

        $previewModel = Preview::query()
            ->where('project_id', $projectModel->id)
            ->whereKey($preview)
            ->firstOrFail();

        $this->authorize('view', $previewModel);

        return view('portal.previews.show', [
            'project' => $projectModel,
            'preview' => $previewModel,
        ]);
    }
}
