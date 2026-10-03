<?php

namespace App\Enums;

/**
 * Where a preview comes from. A static directory is served by Smallgate itself
 * on the preview's own host (docs/adr/0003-preview-delivery.md); an upstream
 * URL lives elsewhere and the portal only links to it. Customers can never
 * choose or influence a target of either kind.
 */
enum PreviewTargetType: string
{
    /** A directory below one of config('previews.allowed_roots'). */
    case StaticDirectory = 'static_directory';

    /** An HTTPS URL whose host is in config('previews.allowed_upstream_hosts'). */
    case UpstreamUrl = 'upstream_url';

    public function label(): string
    {
        return match ($this) {
            self::StaticDirectory => 'Statisches Verzeichnis',
            self::UpstreamUrl => 'Upstream-URL',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::StaticDirectory => 'Pfad unterhalb eines freigegebenen Wurzelverzeichnisses.',
            self::UpstreamUrl => 'HTTPS-URL mit freigegebenem Host.',
        };
    }

    /**
     * Switched on in config('previews.target_types') for this installation.
     */
    public function isEnabled(): bool
    {
        return in_array($this->value, (array) config('previews.target_types', []), true);
    }

    /**
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case->isEnabled()));
    }

    /**
     * The choices for the admin form -- enabled types only.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::enabled())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
