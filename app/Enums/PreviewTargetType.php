<?php

namespace App\Enums;

/**
 * How a preview is served once real provisioning exists. Conceptually prepared
 * only -- see docs/adr/0001-preview-subdomain-architecture.md. Customers can
 * never choose or influence a target of either kind.
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
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
