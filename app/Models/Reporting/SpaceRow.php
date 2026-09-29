<?php

namespace App\Models\Reporting;

use App\Enums\ColorGroup;

/**
 * A row of reporting.spaces: one non-deleted space with type, status, building, managers,
 * live occupancy and asset count (RF_35, RF_53, RF_64, RF_74).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $space_type
 * @property string $status
 * @property ColorGroup $status_color
 * @property bool $is_reservable
 * @property bool $is_occupied derived from reservations, never stored (Q12)
 * @property string $building
 * @property string|null $floor
 * @property string|null $location_detail
 * @property int $capacity
 * @property string|null $managers
 * @property int $asset_count
 */
class SpaceRow extends ReportingView
{
    protected $table = 'reporting.spaces';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_color' => ColorGroup::class,
            'is_reservable' => 'boolean',
            'is_occupied' => 'boolean',
            'capacity' => 'integer',
            'asset_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** "Edificio B · Piso 2 · Ala norte" */
    public function location(): string
    {
        return implode(' · ', array_filter([$this->building, $this->floor ? "Piso {$this->floor}" : null, $this->location_detail]));
    }
}
