<?php

namespace App\Policies;

use App\Enums\PreviewTargetType;
use App\Models\Preview;
use App\Models\User;
use App\Services\Previews\PreviewTargetGuard;

/**
 * A preview inherits its visibility from its project: whoever may view the
 * project may view its previews, and nobody else.
 */
class PreviewPolicy
{
    public function __construct(
        private readonly ProjectPolicy $projects,
        private readonly PreviewTargetGuard $guard,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canAccessPortal();
    }

    public function view(User $user, Preview $preview): bool
    {
        return $preview->project !== null
            && $this->projects->view($user, $preview->project);
    }

    /**
     * Opening the preview itself on its own host (ADR 0003). Checked when the
     * portal hands out a handoff token and again on every request to the
     * preview host, so a revoked right takes effect at once.
     *
     * Customers only ever open a preview that is live; administrators may open
     * one before release to check it. Either way it has to be a static
     * directory -- the only kind Smallgate serves itself -- that the allowlist
     * still accepts, with a valid address on the preview domain.
     */
    public function open(User $user, Preview $preview): bool
    {
        if (! $this->view($user, $preview)) {
            return false;
        }

        if ($preview->target_type !== PreviewTargetType::StaticDirectory
            || ! $this->guard->isAllowed($preview->target_type, $preview->target)
            || $preview->hostUrl() === null) {
            return false;
        }

        return $user->isAdmin() || $preview->status->isVisitable();
    }

    /**
     * Uploading a draft as a ZIP (ADR 0004). Administrators only, and only for
     * a static preview Smallgate serves itself -- an upstream preview lives
     * elsewhere. Customers never upload anything.
     */
    public function upload(User $user, Preview $preview): bool
    {
        return $user->isAdmin()
            && $preview->target_type === PreviewTargetType::StaticDirectory
            && $preview->target_type->isEnabled();
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
