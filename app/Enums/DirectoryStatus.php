<?php

namespace App\Enums;

/**
 * Where a project's folder stands. Set by the request and by the job that
 * creates the folder, never by a form.
 */
enum DirectoryStatus: string
{
    case Pending = 'pending';
    case Created = 'created';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Wird angelegt',
            self::Created => 'Angelegt',
            self::Failed => 'Fehlgeschlagen',
        };
    }
}
