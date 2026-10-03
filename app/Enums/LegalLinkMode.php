<?php

namespace App\Enums;

/**
 * Where the footer's "Impressum" and "Datenschutz" lead: nowhere, to the
 * operator's own page elsewhere, or to a page showing the text pasted in
 * under "Erscheinungsbild".
 */
enum LegalLinkMode: string
{
    case Hidden = 'hidden';
    case Link = 'link';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Hidden => 'Ausblenden',
            self::Link => 'Eigener Link',
            self::Text => 'Eigener Text',
        };
    }
}
