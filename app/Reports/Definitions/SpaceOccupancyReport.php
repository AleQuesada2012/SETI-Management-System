<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\SpaceOccupancyRow;
use App\Queries\Reservations\SpaceOccupancy;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * RF_79: how much each space was used in a period, from the space with the most reservations to the one with the
 * fewest, so the first and last rows answer "which spaces are used most and least". One or more months make it a
 * monthly report; a date range chooses any other period. The same data and booked-time format as the occupancy page
 * (RF_59), through SpaceOccupancy. Owner: @JoseHerrera21.
 */
final class SpaceOccupancyReport extends ReportDefinition
{
    public function code(): string
    {
        return 'occupancy';
    }

    public function columns(): array
    {
        $columns = [];
        foreach (['space', 'reservations', 'booked_hours'] as $key) {
            $columns[$key] = __('reservations.occupancy.columns.'.$key);
        }

        return $columns;
    }

    /** @return Builder<SpaceOccupancyRow> */
    public function query(array $filters): Builder
    {
        return SpaceOccupancy::query(SpaceOccupancy::validate($filters), 'most_reservations');
    }

    public function filterKeys(): array
    {
        return ['month', 'date'];
    }

    public function normalizeFilters(array $filters): array
    {
        parent::normalizeFilters($filters);

        return SpaceOccupancy::validate($filters);
    }

    public function filterFields(): array
    {
        return [
            'month' => ['label' => __('reservations.occupancy.month'), 'type' => 'values', 'options' => SpaceOccupancy::monthChoices()],
            'date' => ['label' => __('reservations.occupancy.period'), 'type' => 'date'],
        ];
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        if (! $row instanceof SpaceOccupancyRow) {
            return parent::cell($row, $key);
        }

        return match ($key) {
            'space' => "{$row->space_code} · {$row->space_name}",
            'booked_hours' => SpaceOccupancy::duration($row->booked_hours),
            default => parent::cell($row, $key),
        };
    }
}
