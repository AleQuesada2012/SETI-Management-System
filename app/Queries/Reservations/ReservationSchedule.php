<?php

namespace App\Queries\Reservations;

use App\Enums\PermissionName;
use App\Enums\ReservationEffectiveStatus;
use App\Models\Reporting\ReservationRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Reservations as every list and report sees them (reporting.reservations, with the derived effective status and
 * the "modificada" label): the reservations of a space (RF_57), the reservation history (RF_52) and later the
 * reservations report (RF_65). Owner: reservations (@JoseHerrera21).
 *
 * It owns the reservations filter contract, following the shared conventions (.ai/rules/reports.md): categorical
 * `values` arrays and an inclusive `date` range whose days are read in Costa Rica time.
 *
 * | Key      | Input                                                                                 |
 * |----------|---------------------------------------------------------------------------------------|
 * | `space`  | `values`: space IDs                                                                   |
 * | `status` | `values`: `upcoming`, `in_progress`, `finished`, `cancelled` and the `modified` label |
 * | `date`   | Optional `from` and `to` dates (`YYYY-MM-DD`)                                         |
 *
 *     $filters = ReservationSchedule::validate(['status' => ['values' => ['cancelled']], 'date' => ['from' => '2026-10-01']]);
 *     ReservationSchedule::history($user, $filters)->paginate(15);
 */
final class ReservationSchedule
{
    /** The `modified` status is the "Modificada" label (version > 1), not a stored or derived state. */
    public const string MODIFIED = 'modified';

    /**
     * Criteria combine with AND; the values inside one criterion combine with OR ("Cancelada or Modificada"). A
     * reservation belongs to a date range when any part of it falls inside it: from 00:00 of `from` to the end of
     * `to`, Costa Rica time, with the same [start, end) semantics as the database. Anything not validated is
     * normalized first, so blank or unknown criteria never restrict.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ReservationRow>
     */
    public static function query(array $filters = []): Builder
    {
        $filters = self::normalize($filters);
        $query = ReservationRow::query();

        if (isset($filters['space'])) {
            $query->whereIn('space_id', $filters['space']['values']);
        }

        if (isset($filters['status'])) {
            $states = array_values(array_diff($filters['status']['values'], [self::MODIFIED]));
            $modified = in_array(self::MODIFIED, $filters['status']['values'], true);

            $query->where(function (Builder $status) use ($states, $modified): void {
                $status->when($states !== [], fn (Builder $q) => $q->whereIn('effective_status', $states))
                    ->when($modified, fn (Builder $q) => $q->orWhere('was_modified', true));
            });
        }

        if (isset($filters['date']['from'])) {
            $query->where('ends_at', '>', Carbon::parse($filters['date']['from'])->startOfDay());
        }

        if (isset($filters['date']['to'])) {
            $query->where('starts_at', '<', Carbon::parse($filters['date']['to'])->addDay()->startOfDay());
        }

        return $query->orderBy('starts_at')->orderBy('id');
    }

    /**
     * The reservation history of a user (RF_52), newest first unless `$direction` is `asc`. Everyone sees the
     * reservations they made or are responsible for; an Administrador also those of the spaces they manage, and the
     * Superusuario all of them: the same reach as ReservationPolicy::update.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ReservationRow>
     */
    public static function history(User $user, array $filters = [], string $direction = 'desc'): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return self::query($filters)
            ->unless($user->isSuperuser(), fn (Builder $query) => $query->where(function (Builder $mine) use ($user): void {
                $mine->where('created_by', $user->id)
                    ->orWhere('responsible_id', $user->id)
                    ->when($user->can(PermissionName::ReservationsManageAny), fn (Builder $q) => $q->orWhereIn(
                        'space_id',
                        fn (QueryBuilder $managed) => $managed->select('space_id')->from('reporting.space_managers')->where('user_id', $user->id),
                    ));
            }))
            ->reorder()
            ->orderBy('starts_at', $direction)
            ->orderBy('id', $direction);
    }

    /**
     * Check the shape and the references of the filters, then normalize them. Unsupported keys, malformed criteria,
     * unknown codes, spaces that never existed, invalid dates and inverted ranges are Spanish validation errors.
     *
     * @param  array<string, mixed>  $filters
     * @return array{space?: array{values: list<int>}, status?: array{values: list<string>}, date?: array{from?: string, to?: string}}
     *
     * @throws ValidationException
     */
    public static function validate(array $filters): array
    {
        $rules = [
            'space' => ['nullable', 'array:values'],
            'space.values' => ['nullable', 'array'],
            'space.values.*' => ['integer', Rule::exists('spaces', 'id')],
            'status' => ['nullable', 'array:values'],
            'status.values' => ['nullable', 'array'],
            'status.values.*' => ['string', Rule::in(self::statuses())],
            'date' => ['nullable', 'array:from,to'],
            'date.from' => ['nullable', 'date_format:Y-m-d'],
            'date.to' => ['nullable', 'date_format:Y-m-d'],
        ];

        $messages = [
            'date.array' => __('reservations.filters.errors.shape', ['filter' => __('reservations.filters.criteria.date')]),
            'date.from.date_format' => __('reservations.filters.errors.date', ['field' => __('reservations.fields.from')]),
            'date.to.date_format' => __('reservations.filters.errors.date', ['field' => __('reservations.fields.to')]),
        ];

        foreach (['space', 'status'] as $key) {
            $label = __("reservations.filters.criteria.{$key}");
            $messages["{$key}.array"] = __('reservations.filters.errors.shape', ['filter' => $label]);
            $messages["{$key}.values.array"] = __('reservations.filters.errors.shape', ['filter' => $label]);

            foreach (['string', 'integer', 'in', 'exists'] as $rule) {
                $messages["{$key}.values.*.{$rule}"] = __('reservations.filters.errors.unknown_value', ['filter' => $label]);
            }
        }

        Validator::make($filters, $rules, $messages)->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (array_diff(array_keys($filters), ['space', 'status', 'date']) as $key) {
                $validator->errors()->add((string) $key, __('reservations.filters.errors.unknown_key', ['key' => $key]));
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
     * Drop empty restrictions, unknown codes, invalid dates and duplicate selections, so `{}` means "every
     * reservation" and equal selections store the same way (validate() reports the invalid ones first).
     *
     * @param  array<string, mixed>  $filters
     * @return array{space?: array{values: list<int>}, status?: array{values: list<string>}, date?: array{from?: string, to?: string}}
     */
    public static function normalize(array $filters): array
    {
        $normalized = [];

        $spaces = collect(is_array($filters['space']['values'] ?? null) ? $filters['space']['values'] : [])
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()->sort()->values()->all();

        if ($spaces !== []) {
            $normalized['space'] = ['values' => $spaces];
        }

        $selected = is_array($filters['status']['values'] ?? null) ? $filters['status']['values'] : [];
        $statuses = array_values(array_filter(self::statuses(), fn (string $code): bool => in_array($code, $selected, true)));

        if ($statuses !== []) {
            $normalized['status'] = ['values' => $statuses];
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
     * The status codes in the order the filters show them: the effective statuses, then the "Modificada" label.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [...array_map(fn (ReservationEffectiveStatus $status): string => $status->value, ReservationEffectiveStatus::cases()), self::MODIFIED];
    }

    /**
     * The status choices with their Spanish labels, the same the badges show.
     *
     * @return list<array{id: string, label: string}>
     */
    public static function statusChoices(): array
    {
        return array_map(fn (string $code): array => [
            'id' => $code,
            'label' => $code === self::MODIFIED ? __('reservations.status.modified') : ReservationEffectiveStatus::from($code)->label(),
        ], self::statuses());
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
