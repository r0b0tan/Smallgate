<?php

/**
 * Serving a draft on its own preview host (ADR 0003): the handoff from the
 * portal, the session on the preview host, the checks on every request and the
 * separation from the portal's routes. The drafts are real files in a
 * temporary allow-listed root.
 */

use App\Enums\PreviewStatus;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\PreviewSession;
use App\Models\Project;
use App\Models\User;
use App\Services\Previews\PreviewAccess;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/smallgate-host-'.bin2hex(random_bytes(6));

    $files = [
        'holzmann/index.html' => '<h1>Zimmerei Holzmann</h1>',
        'holzmann/style.css' => 'body{}',
        'holzmann/ueber-uns/index.html' => '<h1>Über uns</h1>',
        'holzmann/angebot.bin' => 'binary',
        'baenkle/index.html' => '<h1>Bänkle</h1>',
        'secret.txt' => 'neben dem Entwurf',
    ];

    foreach ($files as $path => $content) {
        @mkdir(dirname($this->base.'/'.$path), 0777, true);
        file_put_contents($this->base.'/'.$path, $content);
    }

    config(['previews.allowed_roots' => [$this->base]]);

    $this->customer = Customer::factory()->create();
    $this->user = $this->customerUser($this->customer);
    $this->preview = hostedPreview($this->customer, 'holzmann', $this->base);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->base);
});

function hostedPreview(Customer $customer, string $slug, string $base): Preview
{
    $project = Project::factory()->for_customer($customer)->create();

    return Preview::factory()->for_project($project)->available()->create([
        'slug' => $slug,
        'hostname' => $slug.'.'.config('previews.base_domain'),
        'target' => $base.'/'.$slug,
    ]);
}

function previewUrl(Preview|string $host, string $path = '/'): string
{
    return 'https://'.($host instanceof Preview ? $host->hostname : $host).$path;
}

/**
 * Click "open" in the portal and return the handoff address it sends to.
 */
function handoffFor(TestCase $test, User $user, Preview $preview): string
{
    // A fresh portal session per user: AuthenticateSession signs out a
    // session that was started for someone else.
    $test->flushSession();

    return $test->actingAs($user)
        ->get(route('portal.previews.show', [$preview->project_id, $preview]))
        ->headers->get('Location');
}

/**
 * The whole way in: portal, handoff, session cookie.
 */
function enterPreview(TestCase $test, User $user, Preview $preview): string
{
    $response = $test->get(handoffFor($test, $user, $preview))->assertStatus(303);

    return $response->getCookie(PreviewAccess::COOKIE, decrypt: false)->getValue();
}

function visitPreview(TestCase $test, string $sessionToken, string $url): TestResponse
{
    return $test->withUnencryptedCookie(PreviewAccess::COOKIE, $sessionToken)->get($url);
}

function servedFile(TestResponse $response): string
{
    return $response->baseResponse->getFile()->getRealPath();
}

/* ------------------------------------------------------------ the way in */

it('walks a customer from the portal into the draft', function () {
    $handoff = handoffFor($this, $this->user, $this->preview);

    expect($handoff)->toMatch('#^https://holzmann\.clickit-preview\.test/__smallgate/zugang\?token=[0-9a-f]{64}$#');

    $response = $this->get($handoff)
        ->assertStatus(303)
        ->assertHeader('Location', '/')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    $cookie = $response->getCookie(PreviewAccess::COOKIE, decrypt: false);

    expect($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->getDomain())->toBeNull()
        ->and($cookie->getValue())->toMatch('/^[0-9a-f]{64}$/')
        // The only cookie: no portal session, no CSRF token on a preview host.
        ->and($response->headers->getCookies())->toHaveCount(1);

    $page = visitPreview($this, $cookie->getValue(), previewUrl($this->preview))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

    expect(servedFile($page))->toBe(realpath($this->base.'/holzmann/index.html'));
});

it('stores only hashes of both tokens', function () {
    $handoff = handoffFor($this, $this->user, $this->preview);
    $handoffToken = substr($handoff, -64);
    $sessionToken = $this->get($handoff)->getCookie(PreviewAccess::COOKIE, decrypt: false)->getValue();

    expect(DB::table('preview_handoffs')->where('token_hash', $handoffToken)->exists())->toBeFalse()
        ->and(DB::table('preview_handoffs')->where('token_hash', hash('sha256', $handoffToken))->exists())->toBeTrue()
        ->and(DB::table('preview_sessions')->where('token_hash', $sessionToken)->exists())->toBeFalse()
        ->and(DB::table('preview_sessions')->where('token_hash', hash('sha256', $sessionToken))->exists())->toBeTrue();
});

it('redeems a handoff token only once', function () {
    $handoff = handoffFor($this, $this->user, $this->preview);

    $this->get($handoff)->assertStatus(303);

    $this->get($handoff)
        ->assertNotFound()
        ->assertSee('nicht mehr gültig')
        ->assertCookieMissing(PreviewAccess::COOKIE);
});

it('refuses a handoff token after a minute', function () {
    $handoff = handoffFor($this, $this->user, $this->preview);

    $this->travel(61)->seconds();

    $this->get($handoff)->assertNotFound()->assertCookieMissing(PreviewAccess::COOKIE);
});

it('refuses a handoff token on another preview host, and burns it', function () {
    $other = hostedPreview($this->customer, 'baenkle', $this->base);
    $handoff = handoffFor($this, $this->user, $this->preview);

    $this->get(str_replace($this->preview->hostname, $other->hostname, $handoff))
        ->assertNotFound()
        ->assertCookieMissing(PreviewAccess::COOKIE);

    $this->get($handoff)->assertNotFound();
});

it('refuses a missing or malformed token', function (string $query) {
    $this->get(previewUrl($this->preview, '/__smallgate/zugang'.$query))
        ->assertNotFound()
        ->assertCookieMissing(PreviewAccess::COOKIE);
})->with([
    'none' => '',
    'empty' => '?token=',
    'short' => '?token=abc',
    'uppercase' => '?token='.str_repeat('A', 64),
    'array' => '?token[]=x',
]);

it('throttles the exchange', function () {
    foreach (range(1, 20) as $attempt) {
        $this->get(previewUrl($this->preview, '/__smallgate/zugang?token='.str_repeat('0', 64)))->assertNotFound();
    }

    $this->get(previewUrl($this->preview, '/__smallgate/zugang?token='.str_repeat('0', 64)))->assertTooManyRequests();
});

/* --------------------------------------------------------- without session */

it('sends a visitor without a session to the portal, the same way for every host', function (string $host) {
    $response = $this->get(previewUrl($host, '/irgendwo/seite.html'))
        ->assertRedirect(route('portal.previews.open', ['hostname' => $host]));

    expect($response->headers->getCookies())->toBeEmpty();
})->with([
    'existing preview' => 'holzmann.clickit-preview.test',
    'no preview at all' => 'gibt-es-nicht.clickit-preview.test',
]);

it('sends to the portal on the portal\'s own address, not the preview host\'s scheme', function () {
    // APP_URL is http in tests, the preview host https. Generated URLs follow
    // APP_URL, so the request's scheme must not leak into the portal link.
    $location = $this->get(previewUrl($this->preview))->headers->get('Location');

    expect($location)->toStartWith(rtrim((string) config('app.url'), '/').'/portal/');
});

it('lets the portal send a signed-in visitor straight back with a fresh token', function () {
    $this->actingAs($this->user)
        ->get(route('portal.previews.open', ['hostname' => $this->preview->hostname]))
        ->assertRedirectContains(previewUrl($this->preview, '/__smallgate/zugang?token='));
});

it('answers unknown, foreign and unreleased previews in the portal with the same 404', function () {
    $foreign = hostedPreview(Customer::factory()->create(), 'fremd', $this->base);
    $this->preview->status = PreviewStatus::Draft;
    $this->preview->save();

    foreach (['gibt-es-nicht.clickit-preview.test', $foreign->hostname, $this->preview->hostname] as $host) {
        $this->actingAs($this->user)
            ->get(route('portal.previews.open', ['hostname' => $host]))
            ->assertNotFound();
    }

    expect(DB::table('preview_handoffs')->count())->toBe(0);
});

it('lets an administrator open a preview before release', function () {
    $this->preview->status = PreviewStatus::Draft;
    $this->preview->save();

    $token = enterPreview($this, $this->admin(), $this->preview);

    visitPreview($this, $token, previewUrl($this->preview))->assertOk();
});

/* ------------------------------------------------------- every request */

it('locks the visitor out as soon as a right goes away', function (Closure $revoke) {
    $token = enterPreview($this, $this->user, $this->preview);

    visitPreview($this, $token, previewUrl($this->preview, '/style.css'))->assertOk();

    $revoke($this);

    visitPreview($this, $token, previewUrl($this->preview, '/style.css'))
        ->assertRedirect(route('portal.previews.open', ['hostname' => $this->preview->hostname]));
})->with([
    'preview disabled' => function ($test) {
        $test->preview->status = PreviewStatus::Disabled;
        $test->preview->save();
    },
    'user blocked' => function ($test) {
        $test->user->is_active = false;
        $test->user->save();
    },
    'customer deactivated' => function ($test) {
        $test->customer->is_active = false;
        $test->customer->save();
    },
    'project moved to another customer' => function ($test) {
        $project = $test->preview->project;
        $project->customer_id = Customer::factory()->create()->id;
        $project->save();
    },
    'target taken off the allowlist' => function ($test) {
        DB::table('previews')->where('id', $test->preview->id)->update(['target' => '/etc']);
    },
    'signed out of the portal' => function ($test) {
        $test->actingAs($test->user)->post(route('logout'));
    },
]);

it('ends a session after eight hours', function () {
    $token = enterPreview($this, $this->user, $this->preview);

    $this->travel(8)->hours();
    $this->travel(1)->minute();

    visitPreview($this, $token, previewUrl($this->preview))->assertRedirect();
});

it('binds a session to its own preview', function () {
    $other = hostedPreview($this->customer, 'baenkle', $this->base);
    $token = enterPreview($this, $this->user, $this->preview);

    visitPreview($this, $token, previewUrl($other))
        ->assertRedirect(route('portal.previews.open', ['hostname' => $other->hostname]));
});

it('ends only the sessions of the user who signs out', function () {
    $colleague = $this->customerUser($this->customer);
    enterPreview($this, $this->user, $this->preview);
    enterPreview($this, $colleague, $this->preview);

    $this->flushSession();
    $this->actingAs($this->user)->post(route('logout'));

    expect(PreviewSession::query()->pluck('user_id')->all())->toBe([$colleague->id]);
});

/* -------------------------------------------------------------- the files */

it('serves the files of the draft with their type', function () {
    $token = enterPreview($this, $this->user, $this->preview);

    $css = visitPreview($this, $token, previewUrl($this->preview, '/style.css'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

    expect(servedFile($css))->toBe(realpath($this->base.'/holzmann/style.css'));

    visitPreview($this, $token, previewUrl($this->preview, '/angebot.bin'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Content-Disposition', 'attachment; filename=angebot.bin');
});

it('sends a directory without trailing slash to the slashed path on the same host', function () {
    $token = enterPreview($this, $this->user, $this->preview);

    visitPreview($this, $token, previewUrl($this->preview, '/ueber-uns'))
        ->assertRedirect()
        ->assertHeader('Location', '/ueber-uns/');

    // Laravel's test client trims a trailing slash off every URL, a browser
    // does not -- so this request goes to the kernel as a browser sends it.
    $request = Request::create(previewUrl($this->preview, '/ueber-uns/'), cookies: [PreviewAccess::COOKIE => $token]);

    expect($request->getPathInfo())->toBe('/ueber-uns/');

    $response = TestResponse::fromBaseResponse(app(Kernel::class)->handle($request))->assertOk();

    expect(servedFile($response))->toBe(realpath($this->base.'/holzmann/ueber-uns/index.html'));
});

it('puts the protective headers on every answer of a preview host', function () {
    $responses = [
        'without session' => $this->get(previewUrl($this->preview)),
        'failed exchange' => $this->get(previewUrl($this->preview, '/__smallgate/zugang')),
        'refused method' => $this->post(previewUrl($this->preview)),
    ];

    $token = enterPreview($this, $this->user, $this->preview);

    $responses['file'] = visitPreview($this, $token, previewUrl($this->preview, '/style.css'));
    $responses['missing'] = visitPreview($this, $token, previewUrl($this->preview, '/fehlt.html'));

    foreach ($responses as $response) {
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Robots-Tag', 'noindex');
    }
});

it('lets the browser keep a file but asks every time', function () {
    $token = enterPreview($this, $this->user, $this->preview);

    $first = visitPreview($this, $token, previewUrl($this->preview, '/style.css'));

    expect($first->headers->get('Cache-Control'))->toContain('private')->toContain('no-cache');

    $etag = $first->headers->get('ETag');
    $size = filesize($this->base.'/holzmann/style.css');
    $mtime = filemtime($this->base.'/holzmann/style.css');

    expect($etag)->toBe('"'.dechex($size).'-'.dechex($mtime).'"');

    $this->withUnencryptedCookie(PreviewAccess::COOKIE, $token)
        ->withHeader('If-None-Match', $etag)
        ->get(previewUrl($this->preview, '/style.css'))
        ->assertStatus(304);
});

it('answers a range request in part', function () {
    $token = enterPreview($this, $this->user, $this->preview);

    $this->withUnencryptedCookie(PreviewAccess::COOKIE, $token)
        ->withHeader('Range', 'bytes=0-1')
        ->get(previewUrl($this->preview, '/style.css'))
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-1/6');
});

it('serves nothing outside the draft, hidden or reserved', function (string $path) {
    $token = enterPreview($this, $this->user, $this->preview);

    visitPreview($this, $token, previewUrl($this->preview, $path))
        ->assertNotFound()
        ->assertSee('Nicht gefunden.');
})->with([
    'encoded parent' => '/%2e%2e/secret.txt',
    'encoded slash' => '/..%2fsecret.txt',
    'double encoded' => '/%252e%252e/secret.txt',
    'sibling draft' => '/%2e%2e/baenkle/index.html',
    'hidden file' => '/.env',
    'reserved prefix' => '/__smallgate/index.html',
    'missing file' => '/fehlt.html',
]);

/* ------------------------------------------------- two hosts, one application */

it('never reaches a portal route on a preview host', function (string $path) {
    $this->get(previewUrl($this->preview, $path))
        ->assertRedirect(route('portal.previews.open', ['hostname' => $this->preview->hostname]));

    $token = enterPreview($this, $this->user, $this->preview);

    visitPreview($this, $token, previewUrl($this->preview, $path))
        ->assertNotFound()
        ->assertDontSee('Passwort');
})->with(['/login', '/portal', '/admin', '/passwort-vergessen']);

it('accepts nothing but GET and HEAD on a preview host', function (string $method) {
    $response = $this->call($method, previewUrl($this->preview, '/login'), ['email' => $this->user->email, 'password' => self::PASSWORD])
        ->assertStatus(405)
        ->assertHeader('Allow', 'GET, HEAD');

    expect($response->headers->getCookies())->toBeEmpty();
})->with(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

it('keeps the preview hosts first with cached routes too', function () {
    // Production runs route:cache, which matches through Symfony's compiled
    // matcher instead of Laravel's ordered list.
    $compiled = app('router')->getRoutes()->compile();
    $routes = (new CompiledRouteCollection($compiled['compiled'], $compiled['attributes']))
        ->setRouter(app('router'))
        ->setContainer(app());

    $match = fn (string $method, string $url) => $routes->match(Request::create($url, $method))->getName();

    expect($match('GET', previewUrl($this->preview, '/login')))->toBe('preview-host.show')
        ->and($match('GET', previewUrl($this->preview, '/')))->toBe('preview-host.show')
        ->and($match('GET', previewUrl($this->preview, '/portal')))->toBe('preview-host.show')
        ->and($match('POST', previewUrl($this->preview, '/login')))->toBe('preview-host.refuse')
        ->and($match('POST', previewUrl($this->preview, '/logout')))->toBe('preview-host.refuse')
        ->and($match('GET', previewUrl($this->preview, '/__smallgate/zugang')))->toBe('preview-host.enter')
        ->and($match('GET', route('login')))->toBe('login')
        ->and($routes->match(Request::create(route('login'), 'POST'))->getDomain())->toBeNull();
});
