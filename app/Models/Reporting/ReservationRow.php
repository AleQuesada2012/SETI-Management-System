<?php

namespace App\Models\Reporting;

use App\Enums\ReservationEffectiveStatus;
use App\Enums\ReservationStatus;

/**
 * A row of reporting.reservations with the derived effective status and "modified" label
 * (calendar RF_45, detail RF_51, history RF_52, space reservations RF_57, report RF_65).
 *
 * @property int $id
 * @property int $space_id
 * @property string $space_code
 * @property string $space_name
 * @property string $title
 * @property int $attendees
 * @property string $responsible_name
 * @property ReservationStatus $status
 * @property ReservationEffectiveStatus $effective_status
 * @property bool $was_modified
 */
class ReservationRow extends ReportingView
{
    protected $table = 'reporting.reservations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'local_date' => 'date',
            'status' => ReservationStatus::class,
            'effective_status' => ReservationEffectiveStatus::class,
            'was_modified' => 'boolean',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
