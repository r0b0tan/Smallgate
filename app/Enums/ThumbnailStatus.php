<?php

namespace App\Enums;

enum ThumbnailStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Wird erstellt',
            self::Ready => 'Vorhanden',
            self::Failed => 'Fehlgeschlagen',
        };
    }
}
