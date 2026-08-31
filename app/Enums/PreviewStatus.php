<?php

namespace App\Enums;

enum PreviewStatus: string
{
    case Draft = 'draft';
    case Provisioning = 'provisioning';
    case Available = 'available';
    case Disabled = 'disabled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Provisioning => 'Wird bereitgestellt',
            self::Available => 'Verfügbar',
            self::Disabled => 'Deaktiviert',
            self::Failed => 'Fehlgeschlagen',
        };
    }

    /**
     * Only an available preview is offered to the customer as a link.
     */
    public function isVisitable(): bool
    {
        return $this === self::Available;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-white/5 text-white/60 ring-white/10',
            self::Provisioning => 'bg-sky-400/10 text-sky-300 ring-sky-400/30',
            self::Available => 'bg-accent/10 text-accent ring-accent/30',
            self::Disabled => 'bg-white/5 text-white/40 ring-white/10',
            self::Failed => 'bg-red-400/10 text-red-300 ring-red-400/30',
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
