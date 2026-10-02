<?php

namespace App\Policies;

use App\Models\Preview;
use App\Models\User;

/**
 * Only the customer a preview belongs to answers it, and only while it is
 * actually offered to them. Administrators read feedback but never give it.
 */
class PreviewFeedbackPolicy
{
    public function __construct(private readonly PreviewPolicy $previews) {}

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->canAccessPortal();
    }

    public function create(User $user, Preview $preview): bool
    {
        return $user->isCustomerUser()
            && $preview->status->isVisitable()
            && $preview->version >= 1
            && $this->previews->view($user, $preview);
    }
}
