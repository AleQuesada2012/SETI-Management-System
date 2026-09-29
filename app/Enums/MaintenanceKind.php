<?php

namespace App\Enums;

/**
 * Maintenance kind (maintenance_records.kind). A repair is corrective maintenance (Q10).
 */
enum MaintenanceKind: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Preventive => 'Preventivo',
            self::Corrective => 'Correctivo',
        };
    }
}
