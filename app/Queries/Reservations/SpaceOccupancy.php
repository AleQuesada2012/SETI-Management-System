<?php

namespace App\Queries\Reservations;

use App\Models\Reporting\SpaceOccupancyRow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Occupancy of the spaces in a period (RF_59), and later the occupancy report (RF_79): each space with confirmed
 * reservations in the period, with how many it had and how many hours they booked. Reads reporting.space_occupancy,
 * where a reservation counts on the day it starts (Costa Rica time) and cancelled ones do not count. Spaces deleted
 * later still appear, because their reservations happened. Owner: reservations (@JoseHerrera21).
 *
 * Its filter contract follows the shared conventions (.ai/rules/reports.md):
 *
 * | Key    | Input                                                     |
 * |--------|-----------------------------------------------------------|
 * | `date` | Optional `from` and `to` dates (`YYYY-MM-DD`), inclusive  |
 *
 *     $filters = SpaceOccupancy::validate(['date' => ['from' => '2026-10-01', 'to' => '2026-10-31']]);
 *     SpaceOccupancy::query($filters)->paginate(15);
 */
final class SpaceOccupancy
{
    /**
     * The orders the page offers, the first one the default: by number of reservations or by booked hours, most or
     * fewest first. Each code maps to the column it sorts by and its direction.
     *
     * @var array<string, array{0: string, 1: 'asc'|'desc'}>
     */
    public const array ORDERS = [
        'most_reservations' => ['reservations', 'desc'],
        'fewest_reservations' => ['reservations', 'asc'],
        'most_hours' => ['booked_hours', 'desc'],
        'fewest_hours' => ['booked_hours', 'asc'],
    ];

    /**
     * The spaces with reservations in the period in one of the ORDERS (an unknown code is the default). Ties go by
     * the other measure in the same direction, then by space code. Anything not validated is normalized first, so a
     * blank or invalid bound never restricts.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<SpaceOccupancyRow>
     */
    public static function query(array $filters = [], string $order = 'most_reservations'): Builder
    {
        $filters = self::normalize($filters);
        [$column, $direction] = self::ORDERS[$order] ?? self::ORDERS['most_reservations'];

        return SpaceOccupancyRow::query()
            ->select('space_id', 'space_code', 'space_name')
            ->selectRaw('sum(reservations)::integer AS reservations, sum(booked_hours) AS booked_hours')
            ->when(isset($filters['date']['from']), fn (Builder $query) => $query->where('local_date', '>=', $filters['date']['from']))
            ->when(isset($filters['date']['to']), fn (Builder $query) => $query->where('local_date', '<=', $filters['date']['to']))
            ->groupBy('space_id', 'space_code', 'space_name')
            ->orderBy($column, $direction)
            ->orderBy($column === 'reservations' ? 'booked_hours' : 'reservations', $direction)
            ->orderBy('space_code')
            ->orderBy('space_id');
    }

    /**
     * Check the shape of the filters, then normalize them. Unsupported keys, invalid dates and a final date before
     * the initial one are Spanish validation errors.
     *
     * @param  array<string, mixed>  $filters
     * @return array{date?: array{from?: string, to?: string}}
     *
     * @throws ValidationException
     */
    public static function validate(array $filters): array
    {
        $rules = [
            'date' => ['nullable', 'array:from,to'],
            'date.from' => ['nullable', 'date_format:Y-m-d'],
            'date.to' => ['nullable', 'date_format:Y-m-d'],
        ];

        $messages = [
            'date.array' => __('reservations.occupancy.errors.shape'),
            'date.from.date_format' => __('reservations.occupancy.errors.date', ['field' => __('reservations.fields.from')]),
            'date.to.date_format' => __('reservations.occupancy.errors.date', ['field' => __('reservations.fields.to')]),
        ];

        Validator::make($filters, $rules, $messages)->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (array_diff(array_keys($filters), ['date']) as $key) {
                $validator->errors()->add((string) $key, __('reservations.occupancy.errors.unknown_key', ['key' => $key]));
            }

            $from = self::date($filters['date']['from'] ?? null);
            $to = self::date($filters['date']['to'] ?? null);

            if ($from !== null && $to !== null && $to->lessThan($from)) {
                $validator->errors()->add('date.to', __('reservations.validation.to_after_from'));
            }
        })->validate();

        return self::normalize($filters);
    }

    /**
     * Keep only the valid bounds, so `{}` means "the whole history" and equal periods store the same way.
     *
     * @param  array<string, mixed>  $filters
     * @return array{date?: array{from?: string, to?: string}}
     */
    public static function normalize(array $filters): array
    {
        $normalized = [];

        foreach (['from', 'to'] as $bound) {
            $date = self::date($filters['date'][$bound] ?? null);

            if ($date !== null) {
                $normalized['date'][$bound] = $date->toDateString();
            }
        }

        return $normalized;
    }

    /**
     * The whole calendar month that holds `$day`, as a period: the page opens on the current one and its arrows move
     * from month to month.
     *
     * @return array{date: array{from: string, to: string}}
     */
    public static function month(CarbonInterface $day): array
    {
        $day = CarbonImmutable::instance($day);

        return ['date' => ['from' => $day->startOfMonth()->toDateString(), 'to' => $day->endOfMonth()->toDateString()]];
    }

    /**
     * Booked hours as people read them: 5.75 is "5 h 45 min", 2 is "2 h", 0.75 is "45 min". The view rounds hours to
     * two decimals, so the minutes are rounded back to whole ones.
     */
    public static function duration(float $hours): string
    {
        $minutes = (int) round($hours * 60);
        $whole = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $whole === 0 => __('reservations.occupancy.duration.minutes', ['minutes' => $rest]),
            $rest === 0 => __('reservations.occupancy.duration.hours', ['hours' => $whole]),
            default => __('reservations.occupancy.duration.both', ['hours' => $whole, 'minutes' => $rest]),
        };
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
