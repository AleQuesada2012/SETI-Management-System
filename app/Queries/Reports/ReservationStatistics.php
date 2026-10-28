<?php

namespace App\Queries\Reports;

use App\Enums\ReservationEffectiveStatus;
use App\Models\Reporting\ReservationRow;

/**
 * RF_83: the reservations of a period, counted on their Costa Rica start date (as reporting.space_occupancy does) and
 * analysed by space and effective status. Read live from reporting.reservations, so registering, modifying or
 * cancelling a reservation changes them on the next refresh.
 */
final class ReservationStatistics
{
    /** Chart token of each effective status. */
    private const array COLORS = ['upcoming' => 1, 'in_progress' => 3, 'finished' => 2, 'cancelled' => 4];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        return ReportFilters::validate($filters, ['starts_on' => 'date']);
    }

    /**
     * @param  array<string, mixed>  $filters  normalized by validate()
     * @return array{total: int, statuses: list<array{id: string, label: string, count: int, color: int}>, spaces: list<array{id: int, label: string, count: int, by_status: array<string, int>}>}
     */
    public static function summary(array $filters = []): array
    {
        $reservations = ReservationRow::query();
        ReportFilters::whereDates($reservations, $filters, 'starts_on', 'local_date');

        $counts = (clone $reservations)->toBase()->selectRaw('effective_status, count(*) AS total')->groupBy('effective_status')->pluck('total', 'effective_status');
        $statuses = array_map(fn (ReservationEffectiveStatus $status): array => [
            'id' => $status->value, 'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0), 'color' => self::COLORS[$status->value],
        ], ReservationEffectiveStatus::cases());

        $query = $reservations->toBase()->select('space_id', 'space_code', 'space_name', 'space_deleted')->selectRaw('count(*) AS total');
        foreach (ReservationEffectiveStatus::cases() as $status) {
            $query->selectRaw("count(*) FILTER (WHERE effective_status = ?) AS {$status->value}", [$status->value]);
        }
        $spaces = $query->groupBy('space_id', 'space_code', 'space_name', 'space_deleted')
            ->orderByDesc('total')->orderBy('space_code')->get()
            ->map(function (object $row): array {
                $label = $row->space_code.' · '.$row->space_name;

                return [
                    'id' => (int) $row->space_id, 'count' => (int) $row->total,
                    'label' => $row->space_deleted ? __('reports.filters.removed', ['name' => $label]) : $label,
                    'by_status' => array_combine(
                        array_map(fn (ReservationEffectiveStatus $status): string => $status->value, ReservationEffectiveStatus::cases()),
                        array_map(fn (ReservationEffectiveStatus $status): int => (int) $row->{$status->value}, ReservationEffectiveStatus::cases()),
                    ),
                ];
            })->values()->all();

        return ['total' => array_sum(array_column($statuses, 'count')), 'statuses' => $statuses, 'spaces' => $spaces];
    }
}
