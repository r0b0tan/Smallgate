<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Preview;
use App\Models\Project;
use App\Models\User;

/**
 * Resolve a preview the way the customer portal must: the project through the
 * visibility scope, the preview only within that project. Anything foreign or
 * unknown is the same 404, and the policies have to agree with the scope.
 */
trait ResolvesPortalPreviews
{
    /**
     * @return array{0: Project, 1: Preview}
     */
    protected function resolvePreview(User $user, string $project, string $preview): array
    {
        $projectModel = Project::query()
            ->visibleTo($user)
            ->whereKey($project)
            ->firstOrFail();

        $this->authorize('view', $projectModel);

        $previewModel = Preview::query()
            ->where('project_id', $projectModel->id)
            ->whereKey($preview)
            ->firstOrFail();

        $previewModel->setRelation('project', $projectModel);

        $this->authorize('view', $previewModel);

        return [$projectModel, $previewModel];
    }
}
