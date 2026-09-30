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
 *     ReservationSchedule::query(['space_id' => 3, 'from' => '2026-10-01', 'to' => '2026-10-31'])->get();
 */
final class ReservationSchedule
{
    /**
     * A reservation belongs to a period when any part of it falls inside it: from 00:00 of `from` to the end of
     * `to`, Costa Rica time, with the same [start, end) semantics as the database. Blank filters are ignored.
     *
     * @param  array<string, mixed>  $filters  space_id, from and to (Y-m-d)
     * @return Builder<ReservationRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = ReservationRow::query();

        $spaceId = $filters['space_id'] ?? null;
        $query->when(is_numeric($spaceId), fn (Builder $q) => $q->where('space_id', (int) $spaceId));

        $from = self::date($filters['from'] ?? null);
        $query->when($from !== null, fn (Builder $q) => $q->where('ends_at', '>', $from->startOfDay()));

        $to = self::date($filters['to'] ?? null);
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
