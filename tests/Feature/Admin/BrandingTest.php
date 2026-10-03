<?php

/**
 * "Erscheinungsbild": an administrator sets name, copyright, colours and two
 * logos. Nobody else can; everybody sees the result, the sign-in page and the
 * mails included. Uploaded logos are raster images only and are served from a
 * sandboxed, versioned route.
 */

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\Branding;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(Branding::DISK);
});

/**
 * A real PNG of the given size. The test image has no GD, so
 * UploadedFile::fake()->image() is not available.
 */
function pngBytes(int $width, int $height): string
{
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $rows = str_repeat("\x00".str_repeat("\x00\x00\x00\x00", $width), $height);

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
        .$chunk('IDAT', gzcompress($rows))
        .$chunk('IEND', '');
}

function pngUpload(string $name = 'logo.png', int $width = 64, int $height = 64): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, pngBytes($width, $height));
}

/** Store branding directly and drop the copy the container already holds. */
function brand(array $attributes = []): Branding
{
    $branding = Branding::fromDatabase();
    $branding->forceFill($attributes)->save();

    app()->forgetScopedInstances();

    return Branding::current();
}

/* ------------------------------------------------------------------ access */

it('shows the page to administrators', function () {
    $this->actingAs($this->admin())
        ->get(route('admin.branding.edit'))
        ->assertOk()
        ->assertSee('Erscheinungsbild')
        ->assertSee('Logo für dunkle Flächen');
});

it('answers 404 to customer users', function () {
    $user = $this->customerUser();

    $this->actingAs($user)->get(route('admin.branding.edit'))->assertNotFound();
    $this->actingAs($user)->patch(route('admin.branding.update'), ['name' => 'Gekapert'])->assertNotFound();

    expect(Branding::query()->exists())->toBeFalse();
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.branding.edit'))->assertRedirect(route('login'));
    $this->patch(route('admin.branding.update'), ['name' => 'Gekapert'])->assertRedirect(route('login'));
});

/* ------------------------------------------------------------ text, colours */

it('saves name, copyright and colours and logs the change', function () {
    $admin = $this->admin();

    $this->actingAs($admin)
        ->patch(route('admin.branding.update'), [
            'name' => 'Holzmann Portal',
            'copyright' => 'Holzmann Bau GmbH',
            'brand_color' => '#AA3300',
            'accent_color' => '#0066cc',
        ])
        ->assertRedirect(route('admin.branding.edit'))
        ->assertSessionHasNoErrors();

    $branding = Branding::sole();

    expect($branding->id)->toBe(Branding::ID)
        ->and($branding->name)->toBe('Holzmann Portal')
        ->and($branding->copyright)->toBe('Holzmann Bau GmbH')
        ->and($branding->brand_color)->toBe('#aa3300')
        ->and($branding->accent_color)->toBe('#0066cc');

    $activity = Activity::sole();

    expect($activity->action)->toBe(ActivityAction::BrandingUpdated)
        ->and($activity->actor_id)->toBe($admin->id);
});

it('logs nothing when nothing changed', function () {
    brand(['name' => 'Holzmann Portal']);

    $this->actingAs($this->admin())
        ->patch(route('admin.branding.update'), ['name' => 'Holzmann Portal'])
        ->assertSessionHasNoErrors();

    expect(Activity::count())->toBe(0);
});

it('falls back to the built-in look for empty fields', function () {
    brand(['name' => 'Holzmann Portal', 'brand_color' => '#aa3300']);

    $this->actingAs($this->admin())
        ->patch(route('admin.branding.update'), ['name' => '', 'copyright' => '', 'brand_color' => '', 'accent_color' => ''])
        ->assertSessionHasNoErrors();

    $branding = Branding::sole();

    expect($branding->name)->toBeNull()
        ->and($branding->brand_color)->toBeNull()
        ->and($branding->displayName())->toBe(config('app.name'))
        ->and($branding->copyrightHolder())->toBe(Branding::DEFAULT_COPYRIGHT)
        ->and($branding->stylesheetUrl())->toBeNull();
});

it('rejects anything but a hex colour', function (string $color) {
    $this->actingAs($this->admin())
        ->from(route('admin.branding.edit'))
        ->patch(route('admin.branding.update'), ['brand_color' => $color])
        ->assertSessionHasErrors('brand_color');

    expect(Branding::query()->exists())->toBeFalse();
})->with([
    'named colour' => 'red',
    'short hex' => '#f00',
    'css injection' => '#000000; } body { display: none',
    'no hash' => '344f68',
]);

it('rejects over-long names', function () {
    $this->actingAs($this->admin())
        ->from(route('admin.branding.edit'))
        ->patch(route('admin.branding.update'), ['name' => str_repeat('x', 61)])
        ->assertSessionHasErrors('name');
});

/* --------------------------------------------------- database, mass assign */

it('keeps the logo columns out of mass assignment', function () {
    foreach (['logo_light_path', 'logo_light_mime', 'logo_dark_path', 'logo_dark_mime', 'id'] as $attribute) {
        expect((new Branding)->isFillable($attribute))->toBeFalse("[{$attribute}] darf nicht mass assignable sein.");
    }

    expect(fn () => new Branding(['logo_light_path' => '../../.env']))
        ->toThrow(MassAssignmentException::class);
});

it('enforces a single row and valid values in the database', function (array $row) {
    expect(fn () => DB::table('branding')->insert(['id' => 1, ...$row]))->toThrow(QueryException::class);
})->with([
    'second row' => [['id' => 2]],
    'invalid colour' => [['brand_color' => 'red;}']],
    'upper case colour' => [['accent_color' => '#AA3300']],
    'svg logo' => [['logo_light_path' => 'branding/x.svg', 'logo_light_mime' => 'image/svg+xml']],
    'path without type' => [['logo_dark_path' => 'branding/x.png']],
]);

/* ------------------------------------------------------------------ logos */

it('stores an uploaded logo and serves it sandboxed under its version', function () {
    $this->actingAs($this->admin())
        ->patch(route('admin.branding.update'), ['logo_light' => pngUpload()])
        ->assertSessionHasNoErrors();

    $branding = Branding::sole();

    expect($branding->logo_light_mime)->toBe('image/png')
        ->and($branding->logo_dark_path)->toBeNull();
    Storage::disk(Branding::DISK)->assertExists($branding->logo_light_path);

    app()->forgetScopedInstances();

    $response = $this->get($branding->logoUrl('light'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('replaces and removes logos and deletes the old files', function () {
    $admin = $this->admin();
    $disk = Storage::disk(Branding::DISK);

    $this->actingAs($admin)->patch(route('admin.branding.update'), ['logo_dark' => pngUpload()]);
    $first = Branding::sole()->logo_dark_path;

    $this->actingAs($admin)->patch(route('admin.branding.update'), ['logo_dark' => pngUpload('neu.png', 80, 80)]);
    $second = Branding::sole()->logo_dark_path;

    expect($second)->not->toBe($first);
    $disk->assertMissing($first);
    $disk->assertExists($second);

    $this->actingAs($admin)->patch(route('admin.branding.update'), ['remove_logo_dark' => '1']);

    expect(Branding::sole()->logo_dark_path)->toBeNull()
        ->and(Branding::sole()->logo_dark_mime)->toBeNull();
    $disk->assertMissing($second);
});

it('rejects logos that are not PNG or WebP images of a sensible size', function (Closure $file) {
    $this->actingAs($this->admin())
        ->from(route('admin.branding.edit'))
        ->patch(route('admin.branding.update'), ['logo_light' => $file()])
        ->assertSessionHasErrors('logo_light');

    expect(Branding::query()->exists())->toBeFalse();
    expect(Storage::disk(Branding::DISK)->allFiles())->toBe([]);
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'svg named png' => fn () => UploadedFile::fake()->createWithContent('logo.png',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'html named png' => fn () => UploadedFile::fake()->createWithContent('logo.png', '<html><script>alert(1)</script>'),
    'too small' => fn () => pngUpload('logo.png', 16, 16),
    'too large' => fn () => pngUpload('logo.png', 2400, 100),
    'too heavy' => fn () => UploadedFile::fake()->createWithContent('logo.png', pngBytes(64, 64))->size(600),
]);

it('serves no logo under a foreign version or when there is none', function () {
    $this->get(route('branding.logo', ['variant' => 'hell', 'version' => str_repeat('a', 16)]))->assertNotFound();

    Storage::disk(Branding::DISK)->put('branding/logo.png', pngBytes(64, 64));
    brand(['logo_light_path' => 'branding/logo.png', 'logo_light_mime' => 'image/png']);

    $this->get(route('branding.logo', ['variant' => 'hell', 'version' => str_repeat('a', 16)]))->assertNotFound();
    $this->get(route('branding.logo', ['variant' => 'dunkel', 'version' => Branding::current()->logoVersion('light')]))
        ->assertNotFound();
    $this->get('/erscheinungsbild/logo/../'.Branding::current()->logoVersion('light'))->assertNotFound();
});

/* ------------------------------------------------------------- stylesheet */

it('loads no stylesheet while the built-in colours apply', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('erscheinungsbild.css');
});

it('serves the chosen colours as a stylesheet after app.css', function () {
    $branding = brand(['brand_color' => '#aa3300']);

    $this->get(route('login'))->assertOk()->assertSee($branding->stylesheetUrl(), false);

    $response = $this->get($branding->stylesheetUrl())->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/css; charset=utf-8')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->getContent())->toContain('--color-brand: #aa3300;')
        ->and($response->getContent())->not->toContain('--color-accent');
});

it('does not cache a stylesheet requested under an old version', function () {
    brand(['brand_color' => '#aa3300']);

    $response = $this->get(route('branding.stylesheet', ['v' => 'veraltet']))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});

it('serves colours and logos without a session', function () {
    $branding = brand(['brand_color' => '#aa3300']);
    $admin = $this->admin();

    $this->get($branding->stylesheetUrl())->assertCookieMissing(config('session.cookie'));

    // The browser fetches the stylesheet after the page. A failed form must
    // still go back to the page, not to the stylesheet.
    $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk();
    $this->get($branding->stylesheetUrl())->assertOk();
    $this->actingAs($admin)
        ->patch(route('admin.branding.update'), ['brand_color' => 'rot'])
        ->assertRedirect(route('admin.branding.edit'));
});

/* -------------------------------------------------------------- rendering */

it('shows name and copyright holder across the portal', function () {
    brand(['name' => 'Holzmann Portal', 'copyright' => 'Holzmann Bau GmbH']);

    $this->actingAs($this->customerUser())
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('<title>Ihr Projektstatus · Holzmann Portal</title>', false)
        ->assertSee('Holzmann Portal – zur Startseite')
        ->assertSee('&copy; '.date('Y').' Holzmann Bau GmbH', false)
        ->assertDontSee('CLICKIT DIGITAL');
});

it('keeps the built-in look without any branding', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<span class="font-semibold text-white">Small</span>', false)
        ->assertDontSee('/erscheinungsbild/logo/', false);
});

it('puts the right logo on the dark sign-in panel', function () {
    $disk = Storage::disk(Branding::DISK);
    $disk->put('branding/hell.png', pngBytes(64, 64));
    $disk->put('branding/dunkel.png', pngBytes(64, 64));

    // Only a light logo: it goes onto a light tile, the watermark goes.
    $branding = brand(['logo_light_path' => 'branding/hell.png', 'logo_light_mime' => 'image/png']);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee($branding->logoUrl('light'), false)
        ->assertSee('rounded-lg bg-white p-2.5', false)
        ->assertDontSee('sg-watermark-face', false);

    // A dark logo of its own: shown as it is.
    $branding = brand(['logo_dark_path' => 'branding/dunkel.png', 'logo_dark_mime' => 'image/png']);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee($branding->logoUrl('dark'), false)
        ->assertDontSee('rounded-lg bg-white p-2.5', false);
});

it('carries the name and copyright holder into the mails', function () {
    brand(['name' => 'Holzmann Portal', 'copyright' => 'Holzmann Bau GmbH']);

    $invitation = Invitation::factory()->create();
    $mail = (new InvitationNotification('token'))->toMail($invitation);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Ihr Zugang zum Kundenportal von Holzmann Portal')
        ->and($html)->toContain('Holzmann Portal')
        ->and($html)->toContain('© '.date('Y').' Holzmann Bau GmbH');
});
