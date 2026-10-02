<?php

namespace App\Policies;

use App\Models\User;

/**
 * The activity log is for administrators only. Nobody edits or deletes
 * entries by hand; they expire after the retention period.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
