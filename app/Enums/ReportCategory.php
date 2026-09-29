<?php

namespace App\Enums;

/**
 * Report menu categories (report_types.category, RF_60).
 */
enum ReportCategory: string
{
    case Assets = 'assets';
    case Spaces = 'spaces';
    case Audit = 'audit';
    case Users = 'users';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Assets => 'Activos',
            self::Spaces => 'Espacios',
            self::Audit => 'Auditoría',
            self::Users => 'Usuarios',
        };
    }
}
