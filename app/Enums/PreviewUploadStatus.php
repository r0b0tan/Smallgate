<?php

namespace App\Enums;

/**
 * Where an uploaded draft stands (ADR 0004). Set by the upload request and by
 * the job that unpacks it, never by a form.
 */
enum PreviewUploadStatus: string
{
    case Pending = 'pending';
    case Extracting = 'extracting';
    case Ready = 'ready';
    case Failed = 'failed';

    /** Unpacked once, deleted later to keep the disk from filling up. */
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Wartet',
            self::Extracting => 'Wird entpackt',
            self::Ready => 'Entpackt',
            self::Failed => 'Fehlgeschlagen',
            self::Removed => 'Gelöscht',
        };
    }

    /**
     * Still on its way. The database allows one such upload per preview.
     */
    public function isRunning(): bool
    {
        return $this === self::Pending || $this === self::Extracting;
    }
}
