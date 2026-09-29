<?php

namespace App\Models;

use App\Enums\ColorGroup;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Physical state of an asset with its color group (CONTEXT.md "Condition", ADR-0004).
 *
 * @property int $id
 * @property string $name
 * @property ColorGroup $color_group
 */
#[Fillable(['name', 'color_group', 'description', 'sort_order'])]
class AssetCondition extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color_group' => ColorGroup::class,
            'sort_order' => 'integer',
        ];
    }
}
