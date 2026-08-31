<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only administrators manage accounts. Customer users may edit their own
 * profile and password, which is handled by the profile controller rather than
 * through these abilities.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Blocking or unblocking an account.
     *
     * Administrators must not lock themselves out, so an admin can never
     * toggle their own account.
     */
    public function block(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }
}
