<?php

namespace App\Enums;

/**
 * Lifecycle status of an asset (assets.status, ADR-0004). Physical condition is a separate catalog.
 */
enum AssetStatus: string
{
    case InService = 'in_service';
    case InMaintenance = 'in_maintenance';
    case Lost = 'lost';
    case Decommissioned = 'decommissioned';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::InService => 'En servicio',
            self::InMaintenance => 'En mantenimiento',
            self::Lost => 'Perdido',
            self::Decommissioned => 'De baja',
        };
    }

    /** Lost and decommissioned assets do not count as available (RF_28, RF_29). */
    public function isAvailable(): bool
    {
        return in_array($this, [self::InService, self::InMaintenance], true);
    }
}
