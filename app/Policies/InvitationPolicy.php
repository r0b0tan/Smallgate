<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Resending is only meaningful while the invitation has not been used.
     */
    public function resend(User $user, Invitation $invitation): bool
    {
        return $user->isAdmin() && ! $invitation->isAccepted();
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $user->isAdmin() && ! $invitation->isAccepted();
    }
}
