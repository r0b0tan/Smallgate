<?php

namespace App\Enums;

/**
 * The two answers a customer can give to a draft. Deliberately only two: the
 * customer should never have to think about which button is the right one.
 */
enum FeedbackDecision: string
{
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Passt so',
            self::ChangesRequested => 'Änderung gewünscht',
        };
    }
}
