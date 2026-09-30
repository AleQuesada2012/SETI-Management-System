<?php

namespace App\Queries\Reservations;

use App\Models\Reporting\ReservationRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Reservations as every list and report sees them (reporting.reservations, with the derived effective status and
 * the "modificada" label): the reservations of a space (RF_57), and later the user's history (RF_52) and the
 * reservations report (RF_65). Owner: reservations (@JoseHerrera21).
 *
 * Filters follow the shared filter contract's conventions (.ai/rules/reports.md): categorical `values` arrays and an
 * inclusive `date` range whose days are read in Costa Rica time. RF_65 details the full schema when it is built.
 *
 *     ReservationSchedule::query(['space' => ['values' => [3]], 'date' => ['from' => '2026-10-01', 'to' => '2026-10-31']])->get();
 */
final class ReservationSchedule
{
    /**
     * A reservation belongs to a date range when any part of it falls inside it: from 00:00 of `from` to the end of
     * `to`, Costa Rica time, with the same [start, end) semantics as the database. Both bounds are optional; blank
     * criteria impose no restriction.
     *
     * @param  array{space?: array{values?: list<int|string>}, date?: array{from?: string, to?: string}}  $filters
     * @return Builder<ReservationRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = ReservationRow::query();

        $spaceIds = collect($filters['space']['values'] ?? [])->filter(fn (mixed $id): bool => is_numeric($id))->map(fn (mixed $id): int => (int) $id);
        $query->when($spaceIds->isNotEmpty(), fn (Builder $q) => $q->whereIn('space_id', $spaceIds->all()));

        $from = self::date($filters['date']['from'] ?? null);
        $query->when($from !== null, fn (Builder $q) => $q->where('ends_at', '>', $from->startOfDay()));

        $to = self::date($filters['date']['to'] ?? null);
        $query->when($to !== null, fn (Builder $q) => $q->where('starts_at', '<', $to->addDay()->startOfDay()));

        return $query->orderBy('starts_at')->orderBy('id');
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = Carbon::createFromFormat('Y-m-d', $value);

        return $date instanceof Carbon && $date->format('Y-m-d') === $value ? $date : null;
    }
}
