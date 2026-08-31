<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Customer users have read-only access, and only to projects of their own
 * active customer. All write abilities are administrator-only.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        // Both roles may list projects; the *query* is what restricts a
        // customer user to their own (see Project::scopeVisibleTo).
        return $user->canAccessPortal();
    }

    public function view(User $user, Project $project): bool
    {
        if (! $user->canAccessPortal()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return in_array($project->customer_id, $user->accessibleCustomerIds(), true)
            && $project->customer !== null
            && $project->customer->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Creating and editing the previews of a project.
     */
    public function managePreviews(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }
}
