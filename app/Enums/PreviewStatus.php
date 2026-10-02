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

    /**
     * What provisioning offers to do from the status the preview is in. The
     * button is the only way the status changes, so it has to name the outcome.
     */
    public function provisionActionLabel(): string
    {
        return match ($this) {
            self::Available => 'Erneut bereitstellen',
            self::Disabled => 'Wieder freigeben',
            self::Failed => 'Erneut versuchen',
            self::Draft, self::Provisioning => 'Bereitstellen',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::Provisioning => 'bg-sky-50 text-sky-900 ring-sky-200',
            self::Available => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            self::Disabled => 'bg-slate-100 text-slate-600 ring-slate-200',
            self::Failed => 'bg-red-50 text-red-800 ring-red-200',
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
