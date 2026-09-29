<?php

namespace App\Models;

use App\Enums\ColorGroup;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Operational status of a space: Disponible, En mantenimiento, Inhabilitado (CONTEXT.md). "Ocupado" is never stored (Q12).
 *
 * @property int $id
 * @property string $name
 * @property bool $allows_reservations
 * @property ColorGroup $color_group
 */
#[Fillable(['name', 'allows_reservations', 'color_group', 'sort_order'])]
class SpaceStatus extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allows_reservations' => 'boolean',
            'color_group' => ColorGroup::class,
            'sort_order' => 'integer',
        ];
    }
}
