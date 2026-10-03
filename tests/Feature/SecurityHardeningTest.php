<?php

/**
 * The cross-cutting hardening: CSRF on writes, cookie flags, session config
 * and the absence of any third-party asset.
 */

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\AuthenticateSession;

/* -------------------------------------------------------------------- CSRF */

/*
 * Note on what is asserted here. Laravel's PreventRequestForgery middleware
 * short-circuits when the application is running unit tests
 * (PreventRequestForgery::runningUnitTests()), so a feature test can never
 * observe a real 419 response no matter how the request is built. Asserting
 * 419 would therefore only ever test the test harness.
 *
 * What is asserted instead is the two things that actually protect the app in
 * production: the middleware is registered for every web route, and every
 * write form ships a token for it to check.
 */

it('guards every web route with the security middleware', function () {
    // Read from the kernel, not the router: since Laravel 11 the web group is
    // assembled in bootstrap/app.php and only the kernel has the final list.
    $group = app(Kernel::class)->getMiddlewareGroups()['web'] ?? [];

    expect($group)
        // CSRF protection for every non-read request.
        ->toContain(PreventRequestForgery::class)
        // Binds a session to the current password hash, so a password change
        // invalidates every other session.
        ->toContain(AuthenticateSession::class)
        // Ends the session of a blocked user or deactivated customer on their
        // very next request.
        ->toContain(EnsureAccountIsActive::class);
});

it('exempts no route from csrf protection', function () {
    // PreventRequestForgery::except() would punch a hole in the protection.
    // Nothing in this application has a reason to do that.
    $middleware = new PreventRequestForgery(
        app(), app(Encrypter::class)
    );

    $except = (new ReflectionProperty($middleware, 'except'))->getValue($middleware);

    expect($except)->toBeEmpty();
});

it('puts a csrf token in every write form', function () {
    $admin = $this->admin();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for_customer($customer)->create();

    $this->get(route('login'))->assertOk()->assertSee('name="_token"', escape: false);
    $this->get(route('password.request'))->assertOk()->assertSee('name="_token"', escape: false);

    foreach ([
        route('admin.customers.create'),
        route('admin.customers.edit', $customer),
        route('admin.customers.users.index', $customer),
        route('admin.projects.create'),
        route('admin.projects.edit', $project),
        route('admin.projects.previews.create', $project),
        route('profile.edit'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSee('name="_token"', escape: false);
    }
});

it('sends a write request through successfully with a token', function () {
    $this->actingAs($this->admin())
        ->post(route('admin.customers.store'), [
            '_token' => csrf_token(),
            'name' => 'Mit Token',
            'slug' => 'mit-token',
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect(Customer::whereSlug('mit-token')->exists())->toBeTrue();
});

/* ----------------------------------------------------------------- cookies */

it('configures the session cookie defensively', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.driver'))->toBe('database');
});

it('marks the session cookie httponly on a real response', function () {
    $user = $this->customerUser();

    $response = $this->actingAs($user)->get(route('portal.dashboard'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($candidate) => $candidate->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax');
});

it('requires a secure session cookie in production', function () {
    // Fixed in the production stack like APP_DEBUG, so a stray development
    // .env cannot send the session cookie over plain http.
    expect(file_get_contents(base_path('compose.prod.yaml')))
        ->toContain('SESSION_SECURE_COOKIE: "true"');
});

/* -------------------------------------------------------------------- logs */

it('keeps one-time tokens out of the production access log', function () {
    $nginx = file_get_contents(base_path('docker/nginx/prod.conf.template'));

    // The path is cut after the prefix, and the log format uses the cut path
    // instead of $request and leaves the referrer out.
    expect($nginx)
        ->toContain('~^/einladung/                /einladung/[entfernt];')
        ->toContain('~^/passwort-zuruecksetzen/   /passwort-zuruecksetzen/[entfernt];')
        ->toContain('~^/__smallgate/zugang        /__smallgate/zugang?[entfernt];')
        ->toContain('access_log /var/log/nginx/access.log smallgate;');

    preg_match('/log_format smallgate (.*?);/s', $nginx, $format);

    expect($format[1] ?? '')
        ->toContain('$smallgate_log_uri')
        ->not->toContain('$request ')
        ->not->toContain('$request"')
        ->not->toContain('$request_uri')
        ->not->toContain('$http_referer');
});

it('hands every request on a preview host to the application, GET and HEAD only', function () {
    $nginx = file_get_contents(base_path('docker/nginx/prod.conf.template'));

    preg_match('/server \{\s+listen 80;\s+server_name ~\^(.*?)\n\}\n?$/s', $nginx, $block);

    expect($block)->not->toBeEmpty();

    $preview = $block[0];

    // Exactly the configured domain behind the label, anything else dropped.
    expect($preview)
        ->toContain('if ($preview_domain != "${PREVIEW_BASE_DOMAIN}")')
        ->toContain('return 444;')
        // Everything goes to the front controller; no try_files, so nothing
        // from public/ is ever served on a preview host.
        ->toContain('rewrite ^ /index.php last;')
        ->not->toContain('try_files')
        ->toContain("limit_except GET {\n            deny all;")
        ->toContain('access_log /var/log/nginx/access.log smallgate;')
        // The portal's CSP would break every draft; the app sets the draft's.
        ->not->toContain('Content-Security-Policy');

    expect(file_get_contents(base_path('compose.prod.yaml')))
        ->toContain('PREVIEW_BASE_DOMAIN: ${PREVIEW_BASE_DOMAIN:-}');
});

it('publishes the development ports on loopback only', function () {
    preg_match_all('/^\s+- "([^"]+):\d+"$/m', file_get_contents(base_path('compose.yaml')), $ports);

    expect($ports[1])->not->toBeEmpty();

    foreach ($ports[1] as $published) {
        expect($published)->toStartWith('${DEV_BIND:-127.0.0.1}:');
    }
});

/* --------------------------------------------------------- no third parties */

it('loads no external script, font or stylesheet', function () {
    $user = $this->customerUser();

    $html = $this->actingAs($user)->get(route('portal.dashboard'))->getContent();

    // No analytics, no CDN, no remote fonts -- assets are bundled locally.
    expect($html)
        ->not->toContain('https://fonts.googleapis.com')
        ->not->toContain('https://fonts.gstatic.com')
        ->not->toContain('googletagmanager')
        ->not->toContain('google-analytics')
        ->not->toContain('cdn.jsdelivr.net')
        ->not->toContain('unpkg.com')
        ->not->toContain('cdnjs.cloudflare.com');

    // Every src/href is same-origin or a relative path.
    preg_match_all('/(?:src|href)="([^"]+)"/i', $html, $matches);

    $origin = rtrim((string) config('app.url'), '/');

    foreach ($matches[1] as $url) {
        $isAbsolute = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');

        if (! $isAbsolute) {
            continue; // relative path -- same origin by definition
        }

        expect(str_starts_with($url, $origin))
            ->toBeTrue("Externe Ressource gefunden: {$url}");
    }
});

it('keeps the portal out of search indexes', function () {
    $user = $this->customerUser();

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertSee('name="robots"', escape: false)
        ->assertSee('noindex', escape: false);
});

/* -------------------------------------------------------------- legal pages */

// Imprint and privacy policy, including that pasted text cannot run script:
// see tests/Feature/Admin/LegalLinksTest.php.

/* --------------------------------------------------------------- redirects */

it('sends a guest from the root to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('sends a customer user from the root to the portal dashboard', function () {
    $this->actingAs($this->customerUser())->get('/')->assertRedirect(route('portal.dashboard'));
});

it('sends an administrator from the root to the admin dashboard', function () {
    // Deliberately its own test: AuthenticateSession binds a session to one
    // account's password hash, so swapping identities inside a single session
    // is correctly rejected -- which is a feature, not something to work
    // around by reusing the session here.
    $this->actingAs($this->admin())->get('/')->assertRedirect(route('admin.dashboard'));
});
