<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\ReservationRow;
use App\Queries\Reservations\ReservationSchedule;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * RF_65: the reservations of a period, with every state (active, cancelled, modified, finished). The same data and
 * filter contract as the reservation history (RF_52), through ReservationSchedule. Owner: @JoseHerrera21.
 */
final class ReservationsReport extends ReportDefinition
{
    public function code(): string
    {
        return 'reservations';
    }

    public function columns(): array
    {
        $columns = [];
        foreach (['space', 'date', 'time', 'title', 'attendees', 'responsible', 'status'] as $key) {
            $columns[$key] = __('reports.columns.reservation_'.$key);
        }

        return $columns;
    }

    /** @return Builder<ReservationRow> */
    public function query(array $filters): Builder
    {
        return ReservationSchedule::query(ReservationSchedule::validate($filters));
    }

    public function filterKeys(): array
    {
        return ['status', 'date'];
    }

    public function normalizeFilters(array $filters): array
    {
        parent::normalizeFilters($filters);

        return ReservationSchedule::validate($filters);
    }

    public function filterFields(): array
    {
        return [
            'status' => ['label' => __('reports.filters.labels.reservation_status'), 'type' => 'values', 'options' => ReservationSchedule::statusChoices()],
            'date' => ['label' => __('reports.filters.labels.reservation_date'), 'type' => 'date'],
        ];
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        if (! $row instanceof ReservationRow) {
            return parent::cell($row, $key);
        }

        return match ($key) {
            'space' => "{$row->space_code} · {$row->space_name}",
            'date' => $row->starts_at->format('d/m/Y'),
            'time' => $row->starts_at->format('H:i').'–'.$row->ends_at->format('H:i'),
            'responsible' => $row->responsible_name,
            'status' => $row->effective_status->label().($row->was_modified ? ' · '.__('reservations.status.modified') : ''),
            default => parent::cell($row, $key),
        };
    }
}
