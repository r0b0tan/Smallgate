<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Rules\PreviewHostname;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // The preview hosts run the same application (ADR 0003). Their
            // routes come first and take every path and every method on those
            // hosts, so no portal route -- least of all a login form -- is ever
            // reached there, where a draft's JavaScript would share its origin.
            $base = mb_strtolower((string) config('previews.base_domain'));

            if ($base !== '') {
                Route::domain('{label}.'.$base)
                    ->where(['label' => PreviewHostname::LABEL_PATTERN])
                    ->group(base_path('routes/preview.php'));
            }

            Route::middleware('web')->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reject any request whose Host header the operator has not configured.
        // Password reset and invitation links are absolute URLs built from the
        // request, so an accepted foreign Host would mail a valid token to an
        // attacker's domain. Subdomains of the portal are not trusted. The
        // preview hosts are: exactly one label below the preview domain, where
        // only routes/preview.php and the health check answer. The closure is
        // deliberate -- this callback runs before the configuration is loaded,
        // TrustHosts evaluates it per request. Trusted proxies are configured
        // in AppServiceProvider for the same reason (that API takes a value,
        // not a closure).
        //
        // Note that Laravel skips this check in the local environment and while
        // running tests; see TrustedHostTest for what is asserted instead.
        $middleware->trustHosts(
            at: function (): array {
                $hosts = config('smallgate.trusted_hosts');
                $base = mb_strtolower((string) config('previews.base_domain'));

                if ($base !== '') {
                    $hosts[] = '^'.PreviewHostname::LABEL_PATTERN.'\.'.preg_quote($base, '#').'$';
                }

                return $hosts;
            },
            subdomains: false,
        );

        // AuthenticateSession binds every session to the current password hash,
        // so changing or resetting a password logs out all other sessions.
        // EnsureAccountIsActive makes blocking an account effective immediately.
        $middleware->appendToGroup('web', [
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
        ]);

        // Whether the navigation rail is expanded: set by app.js, read by the
        // layout. A plain "open" carries nothing worth encrypting.
        $middleware->encryptCookies(except: ['sg_rail']);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // Guests are always sent to the login screen; there is no register route.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin()
            ? route('admin.dashboard')
            : route('portal.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );
    })->create();
