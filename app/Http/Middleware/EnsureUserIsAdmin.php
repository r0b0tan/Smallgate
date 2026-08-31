<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the whole /admin area.
 *
 * Customer users get a 404 rather than a 403: the existence of the admin area
 * is not something the portal needs to confirm to them.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin() || ! $user->canAccessPortal()) {
            abort(404);
        }

        return $next($request);
    }
}
