<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocking an account (or deactivating its customer) must take effect on the
 * very next request, not only at the next login. Any already authenticated
 * session of a blocked account is terminated here.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canAccessPortal()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Ihr Zugang ist derzeit nicht aktiv. Bitte wenden Sie sich an Ihren Ansprechpartner.',
            ]);
        }

        return $next($request);
    }
}
