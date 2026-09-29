<?php

namespace App\Enums;

/**
 * Kind of asset event (asset_events.kind): dated facts that change condition or lifecycle status.
 */
enum AssetEventKind: string
{
    case Damage = 'damage';
    case Loss = 'loss';
    case Recovery = 'recovery';
    case Decommission = 'decommission';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Damage => 'Daño',
            self::Loss => 'Pérdida',
            self::Recovery => 'Recuperación',
            self::Decommission => 'Baja',
        };
    }
}
