<?php

namespace App\Queries\Reservations;

use App\Models\Reporting\SpaceOccupancyRow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
 * | Key     | Input                                                              |
 * |---------|--------------------------------------------------------------------|
 * | `month` | `values`: calendar months (`YYYY-MM`); several months combine (OR) |
 * | `date`  | Optional `from` and `to` dates (`YYYY-MM-DD`), inclusive           |
 *
 * Both criteria combine with AND: `month` gives monthly reports, `date` any other period.
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
            ->when(isset($filters['month']), fn (Builder $query) => $query->where(function (Builder $months) use ($filters): void {
                foreach ($filters['month']['values'] as $month) {
                    $first = CarbonImmutable::createFromFormat('!Y-m', $month);
                    $months->orWhereBetween('local_date', [$first->toDateString(), $first->endOfMonth()->toDateString()]);
                }
            }))
            ->groupBy('space_id', 'space_code', 'space_name')
            ->orderBy($column, $direction)
            ->orderBy($column === 'reservations' ? 'booked_hours' : 'reservations', $direction)
            ->orderBy('space_code')
            ->orderBy('space_id');
    }

    /**
     * Check the shape of the filters, then normalize them. Unsupported keys, invalid months or dates and a final date
     * before the initial one are Spanish validation errors.
     *
     * @param  array<string, mixed>  $filters
     * @return array{month?: array{values: list<string>}, date?: array{from?: string, to?: string}}
     *
     * @throws ValidationException
     */
    public static function validate(array $filters): array
    {
        $rules = [
            'month' => ['nullable', 'array:values'],
            'month.values' => ['nullable', 'array'],
            'month.values.*' => ['string', 'date_format:Y-m'],
            'date' => ['nullable', 'array:from,to'],
            'date.from' => ['nullable', 'date_format:Y-m-d'],
            'date.to' => ['nullable', 'date_format:Y-m-d'],
        ];

        $messages = [
            'month.array' => __('reservations.occupancy.errors.month'),
            'month.values.array' => __('reservations.occupancy.errors.month'),
            'month.values.*.string' => __('reservations.occupancy.errors.month'),
            'month.values.*.date_format' => __('reservations.occupancy.errors.month'),
            'date.array' => __('reservations.occupancy.errors.shape'),
            'date.from.date_format' => __('reservations.occupancy.errors.date', ['field' => __('reservations.fields.from')]),
            'date.to.date_format' => __('reservations.occupancy.errors.date', ['field' => __('reservations.fields.to')]),
        ];

        Validator::make($filters, $rules, $messages)->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (array_diff(array_keys($filters), ['month', 'date']) as $key) {
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
     * Keep only the valid months and bounds, without duplicates, so `{}` means "the whole history" and equal periods
     * store the same way.
     *
     * @param  array<string, mixed>  $filters
     * @return array{month?: array{values: list<string>}, date?: array{from?: string, to?: string}}
     */
    public static function normalize(array $filters): array
    {
        $normalized = [];

        $months = collect(is_array($filters['month']['values'] ?? null) ? $filters['month']['values'] : [])
            ->filter(fn (mixed $month): bool => is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1)
            ->unique()->sort()->values()->all();

        if ($months !== []) {
            $normalized['month'] = ['values' => $months];
        }

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
     * The months a report can choose, newest first: the current one and every month with reservations, so the
     * choices stay the same whatever else is filtered.
     *
     * @return list<array{id: string, label: string}>
     */
    public static function monthChoices(): array
    {
        return SpaceOccupancyRow::query()
            ->selectRaw("to_char(local_date, 'YYYY-MM') AS month")
            ->distinct()
            ->pluck('month')
            ->push(now()->format('Y-m'))
            ->unique()->sortDesc()->values()
            ->map(fn (string $month): array => ['id' => $month, 'label' => self::monthName(CarbonImmutable::createFromFormat('!Y-m', $month))])
            ->all();
    }

    /** "Octubre de 2026": how the page heading and the report name a month. */
    public static function monthName(CarbonInterface $month): string
    {
        return Str::ucfirst($month->locale('es')->translatedFormat('F \d\e Y'));
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
