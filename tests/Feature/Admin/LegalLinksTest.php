<?php

/**
 * "Impressum" and "Datenschutz" under "Erscheinungsbild": the built-in page by
 * default, the operator's own page by https link, or switched off entirely --
 * then neither the footer link nor the built-in page exists.
 */

use App\Enums\LegalLinkMode;
use App\Models\Branding;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Store the legal settings directly and drop the container's copy. */
function legalSettings(array $attributes): void
{
    $branding = Branding::fromDatabase();
    $branding->forceFill($attributes)->save();

    app()->forgetScopedInstances();
}

it('links the built-in pages by default', function () {
    expect(Branding::current()->legalMode('imprint'))->toBe(LegalLinkMode::Builtin);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="'.route('legal.imprint').'"', false)
        ->assertSee('href="'.route('legal.privacy').'"', false);

    $this->get(route('legal.imprint'))->assertOk();
    $this->get(route('legal.privacy'))->assertOk();
});

it('lets an administrator link the operator\'s own pages', function () {
    $this->actingAs($this->admin())
        ->patch(route('admin.branding.update'), [
            'imprint_mode' => 'link',
            'imprint_url' => 'https://www.ubecon.de/impressum',
            'privacy_mode' => 'link',
            'privacy_url' => 'https://www.ubecon.de/datenschutz',
        ])
        ->assertSessionHasNoErrors();

    app()->forgetScopedInstances();
    // A different user next: the session still carries the administrator's.
    $this->flushSession();

    $this->actingAs($this->customerUser())
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('href="https://www.ubecon.de/impressum"', false)
        ->assertSee('href="https://www.ubecon.de/datenschutz"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertDontSee('href="'.route('legal.imprint').'"', false);

    // Old bookmarks of the built-in pages follow along.
    $this->get(route('legal.imprint'))->assertRedirect('https://www.ubecon.de/impressum');
    $this->get(route('legal.privacy'))->assertRedirect('https://www.ubecon.de/datenschutz');
});

it('hides a switched-off page everywhere', function () {
    legalSettings(['imprint_mode' => LegalLinkMode::Hidden, 'privacy_mode' => LegalLinkMode::Hidden]);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('>Impressum<', false)
        ->assertDontSee('>Datenschutz<', false);

    $this->get(route('legal.imprint'))->assertNotFound();
    $this->get(route('legal.privacy'))->assertNotFound();
});

it('treats both pages separately', function () {
    legalSettings([
        'imprint_mode' => LegalLinkMode::Link,
        'imprint_url' => 'https://www.ubecon.de/impressum',
        'privacy_mode' => LegalLinkMode::Builtin,
    ]);

    $this->get(route('login'))
        ->assertSee('href="https://www.ubecon.de/impressum"', false)
        ->assertSee('href="'.route('legal.privacy').'"', false);
});

it('keeps the URL when switching away from the link', function () {
    legalSettings(['imprint_mode' => LegalLinkMode::Link, 'imprint_url' => 'https://www.ubecon.de/impressum']);

    $this->actingAs($this->admin())
        ->patch(route('admin.branding.update'), [
            'imprint_mode' => 'builtin',
            'imprint_url' => 'https://www.ubecon.de/impressum',
        ])
        ->assertSessionHasNoErrors();

    $branding = Branding::sole();

    expect($branding->imprint_mode)->toBe(LegalLinkMode::Builtin)
        ->and($branding->imprint_url)->toBe('https://www.ubecon.de/impressum')
        ->and($branding->legalUrl('imprint'))->toBe(route('legal.imprint'));
});

it('requires an https address for a link', function (array $input, string $error) {
    $this->actingAs($this->admin())
        ->from(route('admin.branding.edit'))
        ->patch(route('admin.branding.update'), $input)
        ->assertSessionHasErrors($error);

    expect(Branding::query()->exists())->toBeFalse();
})->with([
    'missing' => [['imprint_mode' => 'link', 'imprint_url' => ''], 'imprint_url'],
    'plain http' => [['privacy_mode' => 'link', 'privacy_url' => 'http://www.ubecon.de/datenschutz'], 'privacy_url'],
    'javascript' => [['imprint_mode' => 'link', 'imprint_url' => 'javascript:alert(1)'], 'imprint_url'],
    'data' => [['imprint_mode' => 'link', 'imprint_url' => 'data:text/html,<script>alert(1)</script>'], 'imprint_url'],
    'relative' => [['imprint_mode' => 'link', 'imprint_url' => '/impressum'], 'imprint_url'],
    'unknown mode' => [['privacy_mode' => 'irgendwo'], 'privacy_mode'],
]);

it('answers 404 to customer users trying to change the links', function () {
    $this->actingAs($this->customerUser())
        ->patch(route('admin.branding.update'), ['imprint_mode' => 'hidden'])
        ->assertNotFound();

    expect(Branding::query()->exists())->toBeFalse();
});

it('enforces modes and https links in the database', function (array $row) {
    expect(fn () => DB::table('branding')->insert(['id' => 1, ...$row]))->toThrow(QueryException::class);
})->with([
    'unknown mode' => [['imprint_mode' => 'irgendwo']],
    'link without url' => [['privacy_mode' => 'link']],
    'javascript url' => [['imprint_url' => 'javascript:alert(1)']],
    'http url' => [['privacy_url' => 'http://www.ubecon.de/datenschutz']],
]);
