<?php

namespace App\Enums;

/**
 * Derived reservation status (reporting.reservations.effective_status). Never stored.
 */
enum ReservationEffectiveStatus: string
{
    case Upcoming = 'upcoming';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Próxima',
            self::InProgress => 'En curso',
            self::Finished => 'Finalizada',
            self::Cancelled => 'Cancelada',
        };
    }
}
