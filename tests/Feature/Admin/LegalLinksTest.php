<?php

/**
 * "Impressum" and "Datenschutz" under "Erscheinungsbild": hidden until set,
 * then either the operator's own page by https link or a text pasted in and
 * shown by the portal. Pasted text is Markdown with all HTML stripped, so it
 * can never run script.
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

it('shows no legal links until something is set', function () {
    expect(Branding::current()->legalMode('imprint'))->toBe(LegalLinkMode::Hidden)
        ->and(Branding::current()->legalMode('privacy'))->toBe(LegalLinkMode::Hidden);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('>Impressum<', false)
        ->assertDontSee('>Datenschutz<', false);

    $this->get(route('legal.imprint'))->assertNotFound();
    $this->get(route('legal.privacy'))->assertNotFound();
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

    // Old bookmarks of the portal's own pages follow along.
    $this->get(route('legal.imprint'))->assertRedirect('https://www.ubecon.de/impressum');
    $this->get(route('legal.privacy'))->assertRedirect('https://www.ubecon.de/datenschutz');
});

it('shows a pasted text on the portal\'s own page', function () {
    $this->actingAs($this->admin())
        ->patch(route('admin.branding.update'), [
            'imprint_mode' => 'text',
            'imprint_text' => "## Angaben gemäß § 5 DDG\n\nUbecon GmbH\nMusterstraße 1\n86609 Donauwörth\n\n- Geschäftsführer: Max Muster",
        ])
        ->assertSessionHasNoErrors();

    app()->forgetScopedInstances();

    $this->get(route('admin.dashboard'))->assertSee('href="'.route('legal.imprint').'"', false);

    $this->get(route('legal.imprint'))
        ->assertOk()
        ->assertSee('<title>Impressum', false)
        ->assertSee('<h2>Angaben gemäß § 5 DDG</h2>', false)
        // Pasted line by line, shown line by line.
        ->assertSee("Ubecon GmbH<br>\nMusterstraße 1<br>\n86609 Donauwörth", false)
        ->assertSee('<li>Geschäftsführer: Max Muster</li>', false);

    $this->get(route('legal.privacy'))->assertNotFound();
});

it('strips HTML and unsafe links from a pasted text', function () {
    legalSettings([
        'privacy_mode' => LegalLinkMode::Text,
        'privacy_text' => "Vorher\n\n<script>alert('x')</script>\n\n<img src=x onerror=alert(1)>\n\n"
            ."[Klick](javascript:alert(1)) [Daten](data:text/html,<script>alert(1)</script>)\n\n"
            .'[Aufsichtsbehörde](https://www.lda.bayern.de)',
    ]);

    $response = $this->get(route('legal.privacy'))->assertOk();
    $html = $response->getContent();

    expect($html)->not->toContain('<script>alert')
        ->and($html)->not->toContain('onerror')
        ->and($html)->not->toContain('href="javascript:')
        ->and($html)->not->toContain('href="data:')
        ->and($html)->toContain('<a href="https://www.lda.bayern.de">Aufsichtsbehörde</a>')
        ->and($html)->toContain('Vorher');
});

it('hides a switched-off page everywhere', function () {
    legalSettings([
        'imprint_mode' => LegalLinkMode::Hidden,
        'imprint_text' => 'Noch gespeichert, aber ausgeblendet.',
        'privacy_mode' => LegalLinkMode::Hidden,
    ]);

    $this->get(route('login'))->assertOk()->assertDontSee('>Impressum<', false);

    $this->get(route('legal.imprint'))->assertNotFound();
    $this->get(route('legal.privacy'))->assertNotFound();
});

it('treats both pages separately', function () {
    legalSettings([
        'imprint_mode' => LegalLinkMode::Link,
        'imprint_url' => 'https://www.ubecon.de/impressum',
        'privacy_mode' => LegalLinkMode::Text,
        'privacy_text' => 'Datenschutz im Kundenportal.',
    ]);

    $this->get(route('login'))
        ->assertSee('href="https://www.ubecon.de/impressum"', false)
        ->assertSee('href="'.route('legal.privacy').'"', false);
});

it('keeps link and text when switching between them', function () {
    legalSettings(['imprint_mode' => LegalLinkMode::Link, 'imprint_url' => 'https://www.ubecon.de/impressum']);

    $this->actingAs($this->admin())
        ->patch(route('admin.branding.update'), [
            'imprint_mode' => 'text',
            'imprint_url' => 'https://www.ubecon.de/impressum',
            'imprint_text' => 'Ubecon GmbH',
        ])
        ->assertSessionHasNoErrors();

    $branding = Branding::sole();

    expect($branding->imprint_mode)->toBe(LegalLinkMode::Text)
        ->and($branding->imprint_url)->toBe('https://www.ubecon.de/impressum')
        ->and($branding->legalUrl('imprint'))->toBe(route('legal.imprint'));
});

it('requires what the chosen option needs', function (array $input, string $error) {
    $this->actingAs($this->admin())
        ->from(route('admin.branding.edit'))
        ->patch(route('admin.branding.update'), $input)
        ->assertSessionHasErrors($error);

    expect(Branding::query()->exists())->toBeFalse();
})->with([
    'link without url' => [['imprint_mode' => 'link', 'imprint_url' => ''], 'imprint_url'],
    'text without text' => [['privacy_mode' => 'text', 'privacy_text' => '   '], 'privacy_text'],
    'plain http' => [['privacy_mode' => 'link', 'privacy_url' => 'http://www.ubecon.de/datenschutz'], 'privacy_url'],
    'javascript' => [['imprint_mode' => 'link', 'imprint_url' => 'javascript:alert(1)'], 'imprint_url'],
    'data' => [['imprint_mode' => 'link', 'imprint_url' => 'data:text/html,<script>alert(1)</script>'], 'imprint_url'],
    'relative' => [['imprint_mode' => 'link', 'imprint_url' => '/impressum'], 'imprint_url'],
    'too long' => [['imprint_mode' => 'text', 'imprint_text' => str_repeat('x', 100001)], 'imprint_text'],
    'retired built-in page' => [['privacy_mode' => 'builtin'], 'privacy_mode'],
]);

it('answers 404 to customer users trying to change the legal pages', function () {
    $this->actingAs($this->customerUser())
        ->patch(route('admin.branding.update'), ['imprint_mode' => 'text', 'imprint_text' => 'Gekapert'])
        ->assertNotFound();

    expect(Branding::query()->exists())->toBeFalse();
});

it('enforces modes, https links and texts in the database', function (array $row) {
    expect(fn () => DB::table('branding')->insert(['id' => 1, ...$row]))->toThrow(QueryException::class);
})->with([
    'retired mode' => [['imprint_mode' => 'builtin']],
    'link without url' => [['privacy_mode' => 'link']],
    'text without text' => [['imprint_mode' => 'text', 'imprint_text' => '  ']],
    'javascript url' => [['imprint_url' => 'javascript:alert(1)']],
    'http url' => [['privacy_url' => 'http://www.ubecon.de/datenschutz']],
    'text too long' => [['privacy_text' => str_repeat('x', 100001)]],
]);
