<?php

namespace App\Models;

use App\Enums\LegalLinkMode;
use App\Policies\BrandingPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The portal's look, as set by an administrator under "Erscheinungsbild":
 * name, footer text, two colours and a logo for light and one for dark
 * backgrounds. One row at most; every empty column falls back to the built-in
 * look, so a fresh installation needs no row at all.
 *
 * The logo columns are not fillable: they point at files on the private disk
 * and are only ever set by the controller after it stored a validated upload.
 */
#[Fillable([
    'name', 'footer_text', 'brand_color', 'accent_color',
    'imprint_mode', 'imprint_url', 'imprint_text',
    'privacy_mode', 'privacy_url', 'privacy_text',
])]
#[UsePolicy(BrandingPolicy::class)]
class Branding extends Model
{
    public const ID = 1;

    public const DISK = 'local';

    public const DIRECTORY = 'branding';

    public const DEFAULT_FOOTER_TEXT = 'SMALLGATE powered by CLICKIT DIGITAL';

    public const DEFAULT_BRAND_COLOR = '#344f68';

    public const DEFAULT_ACCENT_COLOR = '#2f6299';

    /** Internal variant => the word used in the URL. */
    public const LOGO_VARIANTS = ['light' => 'hell', 'dark' => 'dunkel'];

    /** The legal pages, with the route that shows a pasted text. */
    public const LEGAL_PAGES = ['imprint' => 'legal.imprint', 'privacy' => 'legal.privacy'];

    protected $table = 'branding';

    public $incrementing = false;

    /**
     * A row that does not exist yet still has the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'imprint_mode' => 'hidden',
        'privacy_mode' => 'hidden',
    ];

    protected function casts(): array
    {
        return [
            'imprint_mode' => LegalLinkMode::class,
            'privacy_mode' => LegalLinkMode::class,
        ];
    }

    /**
     * The branding in effect, resolved once per request or queued job (see
     * AppServiceProvider).
     */
    public static function current(): self
    {
        return app(self::class);
    }

    public static function fromDatabase(): self
    {
        $branding = self::query()->find(self::ID);

        if ($branding === null) {
            $branding = new self;
            $branding->id = self::ID;
        }

        return $branding;
    }

    public function displayName(): string
    {
        return $this->name ?? (string) config('app.name');
    }

    /**
     * The product name is set in two weights; any other name stays whole.
     *
     * @return array{0: string, 1: string}|null
     */
    public function nameParts(): ?array
    {
        $name = $this->displayName();

        return strcasecmp($name, 'Smallgate') === 0 ? [substr($name, 0, 5), substr($name, 5)] : null;
    }

    public function footerText(): string
    {
        return $this->footer_text ?? self::DEFAULT_FOOTER_TEXT;
    }

    public function legalMode(string $page): LegalLinkMode
    {
        return $this->getAttribute("{$page}_mode");
    }

    /**
     * Where the footer link to "imprint" or "privacy" points, or null when it
     * is not shown at all.
     */
    public function legalUrl(string $page): ?string
    {
        return match ($this->legalMode($page)) {
            LegalLinkMode::Text => route(self::LEGAL_PAGES[$page]),
            LegalLinkMode::Link => $this->getAttribute("{$page}_url"),
            LegalLinkMode::Hidden => null,
        };
    }

    /**
     * The pasted text as HTML. Markdown, so headings and lists of a generated
     * legal text come out right; any HTML in it is stripped and unsafe links
     * (javascript:, data: and the like) are dropped, so nothing pasted can
     * run in the portal. Every line break counts, so an address pasted line by
     * line stays line by line.
     */
    public function legalHtml(string $page): string
    {
        return Str::markdown((string) $this->getAttribute("{$page}_text"), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
    }

    public function hasLogo(string $variant): bool
    {
        return $this->getAttribute("logo_{$variant}_path") !== null;
    }

    public function hasAnyLogo(): bool
    {
        return $this->hasLogo('light') || $this->hasLogo('dark');
    }

    /**
     * Changes with every upload, so the URL can be cached for good.
     */
    public function logoVersion(string $variant): ?string
    {
        $path = $this->getAttribute("logo_{$variant}_path");

        return $path === null ? null : substr(hash('sha256', $path), 0, 16);
    }

    public function logoUrl(string $variant): ?string
    {
        if (! $this->hasLogo($variant)) {
            return null;
        }

        return route('branding.logo', [
            'variant' => self::LOGO_VARIANTS[$variant],
            'version' => $this->logoVersion($variant),
        ]);
    }

    public function hasCustomColors(): bool
    {
        return $this->brand_color !== null || $this->accent_color !== null;
    }

    public function stylesheetVersion(): string
    {
        return substr(hash('sha256', $this->brand_color.'|'.$this->accent_color), 0, 16);
    }

    /**
     * Null while the built-in colours apply: then there is nothing to load.
     */
    public function stylesheetUrl(): ?string
    {
        return $this->hasCustomColors() ? route('branding.stylesheet', ['v' => $this->stylesheetVersion()]) : null;
    }

    /**
     * Overrides for the theme variables in app.css. The shades are mixed by the
     * browser from the one colour that was chosen, so an administrator picks
     * two colours rather than twenty. Only validated hex values get here --
     * the database refuses anything else.
     */
    public function stylesheet(): string
    {
        $variables = [];

        if ($brand = $this->brand_color) {
            $variables += [
                '--color-brand' => $brand,
                '--color-brand-dark' => "color-mix(in oklch, {$brand}, black 22%)",
                '--color-brand-soft' => "color-mix(in oklch, {$brand} 12%, white)",
                '--color-brand-tint' => "color-mix(in oklch, {$brand} 5%, white)",
                '--color-link' => "color-mix(in oklch, {$brand}, black 30%)",
                '--color-ground' => "color-mix(in oklch, {$brand} 16%, white)",
                '--color-slate' => "color-mix(in oklch, {$brand}, black 8%)",
                '--color-slate-light' => "color-mix(in oklch, {$brand}, white 8%)",
                '--color-slate-deep' => "color-mix(in oklch, {$brand}, black 25%)",
                '--color-slate-accent' => "color-mix(in oklch, {$brand} 30%, white)",
            ];
        }

        if ($accent = $this->accent_color) {
            $variables += [
                '--color-accent' => $accent,
                '--color-accent-dark' => "color-mix(in oklch, {$accent}, black 20%)",
                '--color-accent-soft' => "color-mix(in oklch, {$accent} 18%, white)",
                '--color-sky' => "color-mix(in oklch, {$accent} 55%, white)",
            ];
        }

        $lines = array_map(
            fn (string $name, string $value) => "    {$name}: {$value};",
            array_keys($variables),
            $variables,
        );

        return $lines === [] ? '' : ":root {\n".implode("\n", $lines)."\n}\n";
    }

    public function setBrandColorAttribute(?string $value): void
    {
        $this->attributes['brand_color'] = self::normaliseColor($value);
    }

    public function setAccentColorAttribute(?string $value): void
    {
        $this->attributes['accent_color'] = self::normaliseColor($value);
    }

    private static function normaliseColor(?string $value): ?string
    {
        $value = $value === null ? '' : mb_strtolower(trim($value));

        return $value === '' ? null : $value;
    }
}
