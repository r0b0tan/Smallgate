<?php

namespace App\Policies;

use App\Models\Preview;
use App\Models\User;

/**
 * A preview inherits its visibility from its project: whoever may view the
 * project may view its previews, and nobody else.
 */
class PreviewPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function viewAny(User $user): bool
    {
        return $user->canAccessPortal();
    }

    public function view(User $user, Preview $preview): bool
    {
        return $preview->project !== null
            && $this->projects->view($user, $preview->project);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Preview $preview): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Preview $preview): bool
    {
        return $user->isAdmin();
    }
}
