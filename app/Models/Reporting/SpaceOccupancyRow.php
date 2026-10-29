<?php

namespace App\Models\Reporting;

/**
 * Occupancy of a space as SpaceOccupancy reads it (RF_59, later RF_79 and RF_83). reporting.space_occupancy holds
 * one row per space and local day with the confirmed bookings that start that day; the query adds the days of a
 * period together, so a row here is one space with its totals for that period.
 *
 * @property int $space_id
 * @property string $space_code
 * @property string $space_name
 * @property int $reservations
 * @property float $booked_hours
 */
class SpaceOccupancyRow extends ReportingView
{
    protected $table = 'reporting.space_occupancy';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'reservations' => 'integer',
            'booked_hours' => 'float',
            'attendees' => 'integer',
        ];
    }
}
