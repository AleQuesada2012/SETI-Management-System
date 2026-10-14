<?php

namespace App\Queries\Users;

use App\Enums\SystemRole;
use App\Models\Reporting\UserRow;
use App\Models\Role;
use App\Queries\TextSearch;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Users as every list and report sees them (reporting.users, never secrets): the users list (RF_90), search (RF_95),
 * filters (RF_92, RF_101) and the users of a role (RF_100) · @Rubiocai, and the users report (RF_69 ·
 *
 * @AleQuesada2012). It owns the users filter contract (.ai/rules/reports.md § Users schema): validate, normalize,
 * offer choices, then query.
 *
 *     $filters = UserDirectory::validate(['user_type' => ['values' => ['administrators']], 'search' => 'vargas']);
 *     UserDirectory::query($filters)->paginate(15);   // Administradores whose name, email or id matches "vargas"
 */
final class UserDirectory
{
    /** user_type codes (RF_92): Administradores, and "usuarios normales" (neither Administrador nor Superusuario). */
    public const array USER_TYPES = ['administrators', 'regular'];

    /** account_status codes (reporting.users.account_status). */
    public const array ACCOUNT_STATUSES = ['active', 'deactivated'];

    /** The categorical criteria, in the order the page shows them. */
    public const array CRITERIA = ['user_type', 'role', 'account_status'];

    /**
     * Criteria combine with AND; the values inside one criterion combine with OR. Pass validated filters (validate());
     * anything else is normalized first, so blank criteria never restrict.
     *
     * @param  array<string, mixed>  $filters  the same keys in the page, the report and report_generations.filters
     * @return Builder<UserRow>
     */
    public static function query(array $filters = []): Builder
    {
        $filters = self::normalize($filters);
        $query = UserRow::query();

        // RF_95: partial, accent-insensitive match on name, email or id (users.search_text).
        TextSearch::apply($query, 'users', $filters['search'] ?? null);

        if (isset($filters['user_type'])) {
            $query->where(function (Builder $types) use ($filters): void {
                foreach ($filters['user_type']['values'] as $type) {
                    if ($type === 'administrators') {
                        $types->orWhereIn('id', self::holdersOf([SystemRole::Administrador]));
                    } else {
                        // normalize() keeps only USER_TYPES, so anything else is 'regular'.
                        $types->orWhereNotIn('id', self::holdersOf([SystemRole::Administrador, SystemRole::Superusuario]));
                    }
                }
            });
        }

        if (isset($filters['role'])) {
            $query->whereIn('id', fn (QueryBuilder $sub) => $sub
                ->select('user_id')
                ->from('reporting.user_roles')
                ->whereIn('role_id', $filters['role']['values']));
        }

        if (isset($filters['account_status'])) {
            $query->whereIn('account_status', $filters['account_status']['values']);
        }

        return $query->orderBy('name')->orderBy('id');
    }

    /**
     * Check the shape and the references of the filters, then normalize them. Unsupported keys, malformed criteria,
     * unknown codes and role IDs that do not exist are Spanish validation errors that block the list or the report.
     *
     * @param  array<string, mixed>  $filters
     * @return array{search?: string, user_type?: array{values: list<string>}, role?: array{values: list<int>}, account_status?: array{values: list<string>}}
     *
     * @throws ValidationException
     */
    public static function validate(array $filters): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:200'],
            'user_type.values.*' => ['string', Rule::in(self::USER_TYPES)],
            'role.values.*' => ['integer', Rule::exists('roles', 'id')],
            'account_status.values.*' => ['string', Rule::in(self::ACCOUNT_STATUSES)],
        ];
        $messages = [
            'search.string' => __('users.filters.errors.search'),
            'search.max' => __('users.filters.errors.search'),
        ];

        foreach (self::CRITERIA as $key) {
            $label = __("users.filters.criteria.{$key}");
            $rules[$key] = ['nullable', 'array:values'];
            $rules["{$key}.values"] = ['nullable', 'array'];
            $messages["{$key}.array"] = __('users.filters.errors.shape', ['filter' => $label]);
            $messages["{$key}.values.array"] = __('users.filters.errors.shape', ['filter' => $label]);

            foreach (['string', 'integer', 'in', 'exists'] as $rule) {
                $messages["{$key}.values.*.{$rule}"] = __('users.filters.errors.unknown_value', ['filter' => $label]);
            }
        }

        Validator::make($filters, $rules, $messages)->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (array_diff(array_keys($filters), ['search', ...self::CRITERIA]) as $key) {
                $validator->errors()->add((string) $key, __('users.filters.errors.unknown_key', ['key' => $key]));
            }
        })->validate();

        return self::normalize($filters);
    }

    /**
     * Drop empty restrictions, unknown codes and duplicate selections, so `{}` means "every user" and equal
     * selections store the same way (validate() reports the invalid ones first).
     *
     * @param  array<string, mixed>  $filters
     * @return array{search?: string, user_type?: array{values: list<string>}, role?: array{values: list<int>}, account_status?: array{values: list<string>}}
     */
    public static function normalize(array $filters): array
    {
        $normalized = [];

        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        if ($search !== '') {
            $normalized['search'] = $search;
        }

        foreach (self::CRITERIA as $key) {
            $values = self::valuesOf($filters, $key);

            $selected = match ($key) {
                'role' => $values
                    ->filter(fn (mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
                    ->map(fn (int|string $value): int => (int) $value)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()->sort()->values()->all(),
                default => self::codesIn($values, $key === 'user_type' ? self::USER_TYPES : self::ACCOUNT_STATUSES),
            };

            if ($selected !== []) {
                $normalized[$key] = ['values' => $selected];
            }
        }

        return $normalized;
    }

    /**
     * The options of each criterion, from the whole dataset, so selecting one never shrinks the others. Roles are
     * never soft-deleted, so every role is offered.
     *
     * @return array<string, list<array{id: int|string, label: string}>>
     */
    public static function choices(): array
    {
        return [
            'user_type' => array_map(fn (string $code): array => ['id' => $code, 'label' => __("users.filters.options.user_type.{$code}")], self::USER_TYPES),
            'role' => Role::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Role $role): array => ['id' => $role->id, 'label' => $role->name])
                ->all(),
            'account_status' => array_map(fn (string $code): array => ['id' => $code, 'label' => __("users.status.{$code}")], self::ACCOUNT_STATUSES),
        ];
    }

    /**
     * Ids of the users holding any of these roles (reporting.user_roles).
     *
     * @param  list<SystemRole>  $roles
     */
    private static function holdersOf(array $roles): Closure
    {
        return fn (QueryBuilder $sub) => $sub
            ->select('user_id')
            ->from('reporting.user_roles')
            ->whereIn('role', array_map(fn (SystemRole $role): string => $role->value, $roles));
    }

    /**
     * The known codes among the values, without duplicates, in the order of $codes.
     *
     * @param  Collection<int, mixed>  $values
     * @param  list<string>  $codes
     * @return list<string>
     */
    private static function codesIn(Collection $values, array $codes): array
    {
        return array_values(array_filter($codes, fn (string $code): bool => $values->contains($code)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, mixed>
     */
    private static function valuesOf(array $filters, string $key): Collection
    {
        $values = is_array($filters[$key] ?? null) ? ($filters[$key]['values'] ?? []) : [];

        return collect(is_array($values) ? $values : []);
    }
}
