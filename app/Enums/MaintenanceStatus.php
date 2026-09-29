<?php

namespace App\Enums;

/**
 * Maintenance status (maintenance_records.status). "Pending" = scheduled or in progress.
 */
enum MaintenanceStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::InProgress => 'En curso',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Scheduled, self::InProgress], true);
    }
}
