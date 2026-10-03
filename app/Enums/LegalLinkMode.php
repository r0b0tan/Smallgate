<?php

namespace App\Enums;

/**
 * Where the footer's "Impressum" and "Datenschutz" lead: to the built-in page
 * filled from LEGAL_*, to the operator's own page elsewhere, or nowhere.
 */
enum LegalLinkMode: string
{
    case Builtin = 'builtin';
    case Link = 'link';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Builtin => 'Eingebaute Seite',
            self::Link => 'Eigener Link',
            self::Hidden => 'Ausblenden',
        };
    }
}
