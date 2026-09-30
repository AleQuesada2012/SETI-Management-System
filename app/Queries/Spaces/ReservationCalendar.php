<?php

namespace App\Queries\Spaces;

use App\Enums\ReservationStatus;
use App\Models\Reporting\ReservationRow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * RF_45 · The monthly calendar of every space: the confirmed reservations that touch each day of a month, read from
 * reporting.reservations as Agent-DB.md §9 describes (`starts_at < month_end AND ends_at > month_start`). Days are
 * Costa Rica calendar days (config app.timezone); a reservation that crosses midnight marks every day it touches.
 *
 *     ReservationCalendar::month(CarbonImmutable::parse('2026-10-01'));   // ['2026-10-05' => [ReservationRow, …], …]
 */
final class ReservationCalendar
{
    /**
     * @return array<string, list<ReservationRow>> reservations per local day (Y-m-d), each day ordered by start time
     */
    public static function month(CarbonImmutable $month): array
    {
        $timezone = (string) config('app.timezone');
        $monthStart = $month->setTimezone($timezone)->startOfMonth();
        $monthEnd = $monthStart->addMonth();

        $days = [];

        foreach (self::between($monthStart, $monthEnd) as $reservation) {
            $day = CarbonImmutable::parse($reservation->starts_at)->setTimezone($timezone)->startOfDay()->max($monthStart);
            $lastInstant = CarbonImmutable::parse($reservation->ends_at)->setTimezone($timezone)->subSecond()->min($monthEnd->subSecond());

            while ($day <= $lastInstant) {
                $days[$day->toDateString()][] = $reservation;
                $day = $day->addDay();
            }
        }

        return $days;
    }

    /**
     * Confirmed reservations overlapping [$from, $to), in start order.
     *
     * @return Collection<int, ReservationRow>
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return ReservationRow::query()
            ->where('status', ReservationStatus::Confirmed)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->orderBy('starts_at')
            ->orderBy('space_code')
            ->get();
    }
}
