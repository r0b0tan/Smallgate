<?php

/**
 * Host header handling. Password reset and invitation mails contain absolute
 * links, so a request that is allowed to claim any Host it likes can have a
 * valid token mailed to an attacker's domain.
 *
 * Two layers are asserted here: the Host allowlist that rejects such a request,
 * and the pinned root URL that keeps a generated link on the canonical host
 * even if a request with a foreign Host ever reached the application.
 */

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

/*
 * Note on what is asserted here. TrustHosts::shouldSpecifyTrustedHosts() is
 * false in the local environment and while running tests, so a feature test
 * can never observe the middleware rejecting a request -- asserting a 400
 * would only test the test harness (the same situation as CSRF, see
 * SecurityHardeningTest).
 *
 * What is asserted instead is the three things that protect production: the
 * middleware is registered, the patterns it hands to Symfony reject foreign
 * hosts, and URL generation does not depend on the request either way.
 */

function applyTrustedHosts(): void
{
    Request::setTrustedHosts(config('smallgate.trusted_hosts'));
}

afterEach(function () {
    Request::setTrustedHosts([]);
});

it('registers the trusted host middleware globally', function () {
    expect(app(Kernel::class)->getGlobalMiddleware())->toContain(TrustHosts::class);
});

it('derives an anchored pattern for the application host', function () {
    $host = parse_url(config('app.url'), PHP_URL_HOST);

    expect(config('smallgate.trusted_hosts'))
        ->not->toBeEmpty()
        ->toContain('^'.preg_quote($host, '#').'$');
});

it('accepts the configured application host', function () {
    applyTrustedHosts();

    $host = parse_url(config('app.url'), PHP_URL_HOST);

    expect(Request::create('http://'.$host.'/')->getHost())->toBe($host);
});

it('rejects a forged host header', function () {
    applyTrustedHosts();

    Request::create('http://boese.example/')->getHost();
})->throws(SuspiciousOperationException::class);

it('rejects a host that merely contains the application host', function () {
    applyTrustedHosts();

    $host = parse_url(config('app.url'), PHP_URL_HOST);

    Request::create('http://'.$host.'.boese.example/')->getHost();
})->throws(SuspiciousOperationException::class);

it('trusts no subdomain of the application host', function () {
    // subdomains: false in bootstrap/app.php. Preview subdomains are served by
    // a separate service, never by the portal.
    applyTrustedHosts();

    $host = parse_url(config('app.url'), PHP_URL_HOST);

    Request::create('http://vorschau.'.$host.'/')->getHost();
})->throws(SuspiciousOperationException::class);

it('trusts no proxy unless one is configured', function () {
    // Without TRUSTED_PROXIES the X-Forwarded-* headers are ignored, so nobody
    // can claim a different host or scheme by sending them.
    expect(config('smallgate.trusted_proxies'))->toBeNull();

    $request = Request::create('http://localhost/', server: [
        'HTTP_X_FORWARDED_HOST' => 'boese.example',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    expect($request->getHost())->toBe('localhost')
        ->and($request->isSecure())->toBeFalse();
});

it('keeps a password reset link on the canonical host when the request host is forged', function () {
    Notification::fake();

    $user = $this->customerUser();
    $path = parse_url(route('password.email'), PHP_URL_PATH);

    // A request the Host allowlist would reject in production. The generated
    // link must not follow it even if it gets this far.
    $this->post('http://boese.example'.$path, ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPasswordNotification::class,
        function ($notification) use ($user) {
            expect($notification->toMail($user)->actionUrl)
                ->toStartWith(rtrim((string) config('app.url'), '/').'/')
                ->not->toContain('boese.example');

            return true;
        });
});
