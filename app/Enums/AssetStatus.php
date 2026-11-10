<?php

namespace App\Enums;

/**
 * Lifecycle status of an asset (assets.status, ADR-0004). Physical condition is a separate catalog.
 *
 * The status is a state machine (Agent-DB.md §8.4): it changes only through Asset::transitionTo(), as the side effect
 * of an event or a repair, and only along the edges of transitions(). Decommissioned is terminal.
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

    /**
     * At hand and may still change: takes damage, maintenance, repairs and a loss. Lost and decommissioned assets do
     * not count as available (RF_28, RF_29).
     */
    public function isAvailable(): bool
    {
        return in_array($this, self::available(), true);
    }

    /**
     * The available statuses, for queries.
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return [self::InService, self::InMaintenance];
    }

    /**
     * The statuses this one may change to (Agent-DB.md §8.4):
     *
     * | from           | to             | caused by                                                    |
     * |----------------|----------------|--------------------------------------------------------------|
     * | in service     | in maintenance | a repair starts (RF_11)                                      |
     * | in maintenance | in service     | its repair is finished or cancelled                          |
     * | available      | lost           | a loss (RF_28)                                               |
     * | lost           | in service     | a recovery                                                   |
     * | lost           | in maintenance | a recovery while a repair is still in progress               |
     * | any but itself | decommissioned | a decommission (RF_29), terminal                             |
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::InService => [self::InMaintenance, self::Lost, self::Decommissioned],
            self::InMaintenance => [self::InService, self::Lost, self::Decommissioned],
            self::Lost => [self::InService, self::InMaintenance, self::Decommissioned],
            self::Decommissioned => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }
}
