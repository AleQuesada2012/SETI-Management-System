<?php

namespace App\Models\Reporting;

use App\Enums\AssetStatus;
use App\Enums\ColorGroup;

/**
 * A row of reporting.assets: one non-deleted asset with its labels resolved
 * (inventory list RF_07/RF_08, inventory report RF_63, warranties RF_75, statistics RF_80/RF_81).
 *
 * @property int $id
 * @property string $code
 * @property string|null $asset_tag
 * @property string $display_name
 * @property string $category
 * @property string $asset_type
 * @property string|null $brand
 * @property string|null $model
 * @property string $condition
 * @property ColorGroup $condition_color
 * @property AssetStatus $status
 * @property int|null $space_id
 * @property string|null $space_code
 * @property string|null $space_name
 * @property string|null $responsible_name
 * @property string $warranty_state none | valid | expired
 */
class AssetRow extends ReportingView
{
    protected $table = 'reporting.assets';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'condition_color' => ColorGroup::class,
            'acquired_on' => 'date',
            'warranty_starts_on' => 'date',
            'warranty_ends_on' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
