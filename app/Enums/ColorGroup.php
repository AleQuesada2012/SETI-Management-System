<?php

namespace App\Enums;

/**
 * Color group of a condition or space status. Maps to a Flux badge color.
 */
enum ColorGroup: string
{
    case Green = 'green';
    case Amber = 'amber';
    case Red = 'red';
    case Gray = 'gray';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Green => 'Verde',
            self::Amber => 'Ámbar',
            self::Red => 'Rojo',
            self::Gray => 'Gris',
        };
    }

    /** The Flux badge color for this group. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Green => 'green',
            self::Amber => 'amber',
            self::Red => 'red',
            self::Gray => 'zinc',
        };
    }
}
