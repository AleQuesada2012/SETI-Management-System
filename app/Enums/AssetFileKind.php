<?php

namespace App\Enums;

/**
 * What an asset attachment is (asset_files.kind).
 */
enum AssetFileKind: string
{
    case Photo = 'photo';
    case Document = 'document';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Fotografía',
            self::Document => 'Documento',
        };
    }
}
