<?php

namespace App\Queries\Assets;

use App\Enums\AssetStatus;
use App\Enums\ColorGroup;
use App\Models\AssetCategory;
use App\Models\AssetCondition;
use App\Models\Brand;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The inventory's filter contract (.ai/rules/reports.md § Shared filter contract, inventory schema): validation,
 * normalization and option choices, shared by the inventory list (RF_07, RF_08, RF_30) and the inventory report
 * (RF_63). AssetInventory applies the normalized filters.
 *
 * | Key                | Input                                                        | include_missing |
 * |--------------------|--------------------------------------------------------------|-----------------|
 * | `search`           | text: name, brand, model, code, tag or serial                | no              |
 * | `brand`            | `values`: brand ids                                          | yes             |
 * | `model`            | `contains`: text                                             | yes             |
 * | `category`         | `values`: asset-category ids (RF_24; extension, see below)   | no              |
 * | `condition`        | `values`: physical-condition ids                             | no              |
 * | `condition_color`  | `values`: green, amber, red                                  | no              |
 * | `lifecycle_status` | `values`: in_service, in_maintenance, lost, decommissioned   | no              |
 * | `space`            | `values`: space ids                                          | yes             |
 * | `responsible_user` | `values`: user ids (RF_30)                                   | yes             |
 * | `acquired_on`      | `from` and/or `to`, YYYY-MM-DD, inclusive                    | yes             |
 *
 * `category` is not in the contract approved on 2026-09-29; it is added for RF_24's "filtrar activos según su
 * categoría" and awaits the architect's approval in the pull request that introduces it.
 *
 *     InventoryFilters::normalize(['brand' => ['values' => [12, 12], 'include_missing' => true], 'model' => []]);
 *     // ['brand' => ['values' => [12], 'include_missing' => true]]
 */
final class InventoryFilters
{
    /** Criteria whose input is a list of catalog ids, with the table that must hold each id. */
    private const ID_CRITERIA = [
        'brand' => 'brands',
        'category' => 'asset_categories',
        'condition' => 'asset_conditions',
        'space' => 'spaces',
        'responsible_user' => 'users',
    ];

    /** Criteria that may also select records without a value. */
    private const MISSING_ALLOWED = ['brand', 'model', 'space', 'responsible_user', 'acquired_on'];

    public const KEYS = [
        'search', 'brand', 'model', 'category', 'condition', 'condition_color', 'lifecycle_status', 'space',
        'responsible_user', 'acquired_on',
    ];

    /**
     * Validate the input and return it normalized: trimmed text, unique sorted selections, no empty restrictions.
     * `[]` means no restriction at all.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException in Spanish, keyed by the failing criterion or member (e.g. `acquired_on.to`)
     */
    public static function normalize(array $input): array
    {
        $unknown = array_diff(array_map('strval', array_keys($input)), self::KEYS);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'filters' => __('assets.filters.unknown', ['keys' => implode(', ', $unknown)]),
            ]);
        }

        $data = Validator::make($input, self::rules($input), attributes: self::attributes())->validate();

        $normalized = [];

        $search = trim((string) ($data['search'] ?? ''));
        if ($search !== '') {
            $normalized['search'] = $search;
        }

        foreach (['brand', 'category', 'condition', 'space', 'responsible_user'] as $key) {
            $values = array_values(array_unique(array_map('intval', $data[$key]['values'] ?? [])));
            sort($values);
            $normalized[$key] = self::criterion('values', $values, $key, $data);
        }

        foreach (['condition_color', 'lifecycle_status'] as $key) {
            $values = array_values(array_unique(array_map('strval', $data[$key]['values'] ?? [])));
            sort($values);
            $normalized[$key] = self::criterion('values', $values, $key, $data);
        }

        $contains = trim((string) ($data['model']['contains'] ?? ''));
        $normalized['model'] = self::criterion('contains', $contains === '' ? [] : $contains, 'model', $data);

        $range = array_filter([
            'from' => $data['acquired_on']['from'] ?? null,
            'to' => $data['acquired_on']['to'] ?? null,
        ], fn (?string $date): bool => $date !== null && $date !== '');
        $missing = (bool) ($data['acquired_on']['include_missing'] ?? false);
        $normalized['acquired_on'] = $range === [] && ! $missing ? null : [...$range, ...($missing ? ['include_missing' => true] : [])];

        return array_filter($normalized, fn (mixed $value): bool => $value !== null);
    }

    /**
     * The options each criterion offers, stable whatever else is selected: current catalog entries plus removed
     * ones still referenced by an asset, labeled as such; active users plus deactivated custodians.
     *
     * @return array{
     *     brand: array<int, string>, category: array<int, string>, condition: array<int, string>,
     *     condition_color: array<string, string>, lifecycle_status: array<string, string>,
     *     space: array<int, string>, responsible_user: array<int, string>
     * }
     */
    public static function choices(): array
    {
        $referenced = fn (string $column) => DB::table('reporting.assets')->whereNotNull($column)->select($column);

        return [
            'brand' => self::catalog(Brand::withTrashed()->orderBy('name'), $referenced('brand_id'), fn (Brand $b): string => $b->name, 'removed_f'),
            'category' => self::catalog(AssetCategory::withTrashed()->orderBy('name'), $referenced('category_id'), fn (AssetCategory $c): string => $c->name, 'removed_f'),
            'condition' => self::catalog(AssetCondition::withTrashed()->orderBy('sort_order'), $referenced('condition_id'), fn (AssetCondition $c): string => "{$c->name} ({$c->color_group->label()})", 'removed_f'),
            'condition_color' => collect([ColorGroup::Green, ColorGroup::Amber, ColorGroup::Red])
                ->mapWithKeys(fn (ColorGroup $color): array => [$color->value => $color->label()])->all(),
            'lifecycle_status' => collect(AssetStatus::cases())
                ->mapWithKeys(fn (AssetStatus $status): array => [$status->value => $status->label()])->all(),
            'space' => self::catalog(Space::withTrashed()->orderBy('code'), $referenced('space_id'), fn (Space $s): string => "{$s->code} · {$s->name}", 'removed_m'),
            'responsible_user' => User::query()
                ->where(fn (Builder $q) => $q->whereNull('deactivated_at')->orWhereIn('id', $referenced('responsible_user_id')))
                ->orderBy('name')->get(['id', 'name', 'deactivated_at'])
                ->mapWithKeys(fn (User $u): array => [$u->id => $u->isActive() ? $u->name : $u->name.' '.__('assets.filters.deactivated')])
                ->all(),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  a catalog including its removed entries (withTrashed), already ordered
     * @param  callable(TModel): string  $label
     * @return array<int, string>
     */
    private static function catalog(Builder $query, QueryBuilder $referenced, callable $label, string $removedKey): array
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('deleted_at'))->orWhereIn($q->qualifyColumn('id'), $referenced))
            ->get()
            ->mapWithKeys(fn (Model $model): array => [
                (int) $model->getKey() => $model->getAttribute('deleted_at') === null
                    ? $label($model)
                    : $label($model).' '.__('assets.filters.'.$removedKey),
            ])
            ->all();
    }

    /**
     * One normalized criterion, or null when it imposes no restriction.
     *
     * @param  list<int|string>|string  $selection
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function criterion(string $member, array|string $selection, string $key, array $data): ?array
    {
        $missing = in_array($key, self::MISSING_ALLOWED, true) && (bool) ($data[$key]['include_missing'] ?? false);

        if ($selection === [] && ! $missing) {
            return null;
        }

        return [
            ...($selection === [] ? [] : [$member => $selection]),
            ...($missing ? ['include_missing' => true] : []),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<string, list<mixed>>
     */
    private static function rules(array $input): array
    {
        $rules = ['search' => ['nullable', 'string', 'max:200']];

        foreach (self::ID_CRITERIA as $key => $table) {
            $rules += self::listRules($key, ['integer', Rule::exists($table, 'id')]);
        }

        $rules += self::listRules('condition_color', ['string', Rule::in([ColorGroup::Green->value, ColorGroup::Amber->value, ColorGroup::Red->value])]);
        $rules += self::listRules('lifecycle_status', ['string', Rule::in(array_column(AssetStatus::cases(), 'value'))]);

        $rules['model'] = ['nullable', 'array:contains,include_missing'];
        $rules['model.contains'] = ['nullable', 'string', 'max:120'];
        $rules['model.include_missing'] = ['boolean'];

        $rules['acquired_on'] = ['nullable', 'array:from,to,include_missing'];
        $rules['acquired_on.from'] = ['nullable', 'date_format:Y-m-d'];
        $rules['acquired_on.to'] = ['nullable', 'date_format:Y-m-d'];
        $rules['acquired_on.include_missing'] = ['boolean'];

        if (filled(Arr::get($input, 'acquired_on.from')) && filled(Arr::get($input, 'acquired_on.to'))) {
            $rules['acquired_on.to'][] = 'after_or_equal:acquired_on.from';
        }

        return $rules;
    }

    /**
     * @param  list<mixed>  $valueRules
     * @return array<string, list<mixed>>
     */
    private static function listRules(string $key, array $valueRules): array
    {
        $missing = in_array($key, self::MISSING_ALLOWED, true);

        return [
            $key => ['nullable', $missing ? 'array:values,include_missing' : 'array:values'],
            "{$key}.values" => ['nullable', 'array'],
            "{$key}.values.*" => $valueRules,
            ...($missing ? ["{$key}.include_missing" => ['boolean']] : []),
        ];
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        $names = __('assets.filters.fields');

        $attributes = [];
        foreach ($names as $key => $name) {
            foreach (['', '.values', '.values.*', '.contains', '.include_missing'] as $suffix) {
                $attributes[$key.$suffix] = $name;
            }
        }

        $attributes['acquired_on.from'] = __('assets.filters.acquired_from');
        $attributes['acquired_on.to'] = __('assets.filters.acquired_to');

        return $attributes;
    }
}
