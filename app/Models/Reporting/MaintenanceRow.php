<?php

namespace App\Models\Reporting;

use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use Illuminate\Support\Carbon;

/**
 * A row of reporting.maintenance: one preventive or corrective intervention on an asset (RF_10, RF_11, RF_26, RF_27;
 * maintenance report RF_66 and statistics RF_82).
 *
 * @property int $id
 * @property int $asset_id
 * @property string $asset_code
 * @property string|null $asset_tag
 * @property string $asset_name
 * @property MaintenanceKind $kind
 * @property MaintenanceStatus $status
 * @property Carbon|null $completed_on
 * @property Carbon $reference_date
 * @property string $description
 * @property string|null $performed_by
 * @property string|null $registered_by_name
 */
class MaintenanceRow extends ReportingView
{
    protected $table = 'reporting.maintenance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MaintenanceKind::class,
            'status' => MaintenanceStatus::class,
            'scheduled_for' => 'date',
            'started_on' => 'date',
            'completed_on' => 'date',
            'reference_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
