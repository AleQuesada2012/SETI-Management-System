<?php

namespace App\Enums;

/**
 * Stored reservation status (reservations.status). Finished/in progress are derived (ADR-0005).
 */
enum ReservationStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmada',
            self::Cancelled => 'Cancelada',
        };
    }
}
