<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case WaitingForFeedback = 'waiting_for_feedback';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Active => 'Aktiv',
            self::WaitingForFeedback => 'Warte auf Feedback',
            self::Completed => 'Abgeschlossen',
            self::Archived => 'Archiviert',
        };
    }

    /**
     * Tailwind classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-white/5 text-white/60 ring-white/10',
            self::Active => 'bg-accent/10 text-accent ring-accent/30',
            self::WaitingForFeedback => 'bg-amber-400/10 text-amber-300 ring-amber-400/30',
            self::Completed => 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30',
            self::Archived => 'bg-white/5 text-white/40 ring-white/10',
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
