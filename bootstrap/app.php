<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reject any request whose Host header the operator has not configured.
        // Password reset and invitation links are absolute URLs built from the
        // request, so an accepted foreign Host would mail a valid token to an
        // attacker's domain. Subdomains are not trusted: preview subdomains are
        // a separate service. The closure is deliberate -- this callback runs
        // before the configuration is loaded, TrustHosts evaluates it per
        // request. Trusted proxies are configured in AppServiceProvider for the
        // same reason (that API takes a value, not a closure).
        //
        // Note that Laravel skips this check in the local environment and while
        // running tests; see TrustedHostTest for what is asserted instead.
        $middleware->trustHosts(
            at: fn (): array => config('smallgate.trusted_hosts'),
            subdomains: false,
        );

        // AuthenticateSession binds every session to the current password hash,
        // so changing or resetting a password logs out all other sessions.
        // EnsureAccountIsActive makes blocking an account effective immediately.
        $middleware->appendToGroup('web', [
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
        ]);

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
