<?php

namespace App\Models\Reporting;

use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use Illuminate\Support\Carbon;

/**
 * A row of reporting.asset_events: a dated damage, loss, recovery or decommission of an asset (RF_13, RF_22, RF_28,
 * RF_29; disposals report RF_76). `asset_display_name` and `asset_status` exist when ReportFilters::withCurrentAsset
 * joins them.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $asset_code
 * @property string|null $asset_tag
 * @property string $asset_name
 * @property AssetEventKind $kind
 * @property Carbon $occurred_on
 * @property string $description
 * @property string|null $registered_by_name
 * @property int $evidence_count
 * @property string|null $asset_display_name
 * @property AssetStatus|null $asset_status
 */
class AssetEventRow extends ReportingView
{
    protected $table = 'reporting.asset_events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AssetEventKind::class,
            'asset_status' => AssetStatus::class,
            'occurred_on' => 'date',
            'created_at' => 'datetime',
        ];
    }
}
