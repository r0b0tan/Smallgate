<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * After a password change or reset, every other session of that account must
 * stop working immediately -- otherwise a stolen session survives exactly the
 * event that was meant to recover from it.
 *
 * Laravel's AuthenticateSession middleware already invalidates other sessions
 * because they carry the old password hash. Deleting the rows on top of that
 * makes it explicit and independent of the session driver's expiry.
 */
trait RevokesSessions
{
    /**
     * Delete the account's stored sessions, optionally keeping the current one.
     */
    protected function revokeSessions(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();

        // Invalidate "remember me" cookies issued for the old password too.
        $user->forceFill(['remember_token' => null])->save();
    }
}
