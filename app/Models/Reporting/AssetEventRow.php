<?php

namespace App\Models\Reporting;

use App\Enums\AssetEventKind;
use Illuminate\Support\Carbon;

/**
 * A row of reporting.asset_events: one damage, loss, recovery or decommission with the user who registered it resolved
 * (asset history RF_09; losses and decommissions report RF_76).
 *
 * @property int $id
 * @property int $asset_id
 * @property string $asset_code
 * @property AssetEventKind $kind
 * @property Carbon $occurred_on
 * @property string $description
 * @property int|null $registered_by
 * @property string|null $registered_by_name
 * @property int $evidence_count
 * @property Carbon $created_at
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
            'occurred_on' => 'date',
            'evidence_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
