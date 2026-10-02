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
            self::Draft => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::Active => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            self::WaitingForFeedback => 'bg-amber-50 text-amber-900 ring-amber-200',
            self::Completed => 'bg-sky-50 text-sky-900 ring-sky-200',
            self::Archived => 'bg-slate-100 text-slate-600 ring-slate-200',
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
