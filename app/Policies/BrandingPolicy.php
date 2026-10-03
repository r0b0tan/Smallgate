<?php

namespace App\Policies;

use App\Models\Branding;
use App\Models\User;

/**
 * Only administrators change the portal's look. Reading it needs no ability:
 * name, colours and logos are shown to everybody, the sign-in page included.
 */
class BrandingPolicy
{
    public function update(User $user, Branding $branding): bool
    {
        return $user->isAdmin();
    }
}
