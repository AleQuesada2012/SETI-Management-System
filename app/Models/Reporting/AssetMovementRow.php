<?php

namespace App\Models\Reporting;

use App\Enums\MovementType;
use Illuminate\Support\Carbon;

/**
 * A row of reporting.asset_movements: one entry, transfer or exit with its spaces and user resolved
 * (asset movements RF_25, asset history RF_09, movements report RF_67).
 *
 * @property int $id
 * @property int $asset_id
 * @property string $asset_code
 * @property MovementType $type
 * @property int|null $from_space_id
 * @property string|null $from_space_code
 * @property string|null $from_space_name
 * @property int|null $to_space_id
 * @property string|null $to_space_code
 * @property string|null $to_space_name
 * @property Carbon $moved_at
 * @property int|null $moved_by
 * @property string|null $moved_by_name
 * @property string|null $reason
 */
class AssetMovementRow extends ReportingView
{
    protected $table = 'reporting.asset_movements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'moved_at' => 'datetime',
        ];
    }
}
