<?php

namespace App\Enums;

/**
 * Type of an asset movement (asset_movements.type, ADR-0003).
 */
enum MovementType: string
{
    case Entry = 'entry';
    case Transfer = 'transfer';
    case Exit = 'exit';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Ingreso',
            self::Transfer => 'Traslado',
            self::Exit => 'Salida',
        };
    }
}
