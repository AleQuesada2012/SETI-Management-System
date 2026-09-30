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
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * The inventory's filter contract (.ai/rules/reports.md § Shared filter contract, inventory schema): validation,
 * normalization and option choices. Owner: assets (@DavidZamoraB). The inventory list (RF_07, RF_08, RF_30) and the
 * inventory report (RF_63, App\Reports\Definitions\AssetInventoryReport) both use it; AssetInventory applies it.
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
 *     InventoryFilters::validate(['brand' => ['values' => ['12', 12], 'include_missing' => true], 'model' => []]);
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

    /**
     * The supported keys, in display order.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            'search', 'brand', 'model', 'category', 'condition', 'condition_color', 'lifecycle_status', 'space',
            'responsible_user', 'acquired_on',
        ];
    }

    /**
     * Validate the input and return it normalized: trimmed text, unique sorted selections, no empty restrictions.
     * `[]` means no restriction at all.
     *
     * @param  array<array-key, mixed>  $filters
     * @return array<string, mixed>
     *
     * @throws ValidationException in Spanish, keyed by the failing key or member (e.g. `brand.values.0`)
     */
    public static function validate(array $filters): array
    {
        $data = Validator::make($filters, self::rules($filters), self::messages())
            ->after(function (ValidatorInstance $validator) use ($filters): void {
                foreach (array_diff(array_map('strval', array_keys($filters)), self::keys()) as $key) {
                    $validator->errors()->add($key, __('assets.filters.errors.unknown', ['key' => $key]));
                }
            })
            ->validate();

        $normalized = [];

        $search = trim((string) ($data['search'] ?? ''));
        if ($search !== '') {
            $normalized['search'] = $search;
        }

        foreach (array_keys(self::ID_CRITERIA) as $key) {
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

        // Display order, without the criteria that impose no restriction.
        return array_filter(
            array_replace(array_fill_keys(self::keys(), null), $normalized),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * The options each criterion offers, stable whatever else is selected: current catalog entries plus removed
     * ones still referenced by an asset, and every user, with removed and deactivated entries labeled as such.
     *
     * @return array<string, list<array{id: int|string, label: string}>>
     */
    public static function choices(): array
    {
        $referenced = fn (string $column) => DB::table('reporting.assets')->whereNotNull($column)->select($column);

        return [
            'brand' => self::catalog(Brand::withTrashed()->orderBy('name'), $referenced('brand_id'), fn (Brand $b): string => $b->name),
            'category' => self::catalog(AssetCategory::withTrashed()->orderBy('name'), $referenced('category_id'), fn (AssetCategory $c): string => $c->name),
            'condition' => self::catalog(AssetCondition::withTrashed()->orderBy('sort_order'), $referenced('condition_id'), fn (AssetCondition $c): string => "{$c->name} ({$c->color_group->label()})"),
            'condition_color' => array_map(
                fn (ColorGroup $color): array => ['id' => $color->value, 'label' => $color->label()],
                [ColorGroup::Green, ColorGroup::Amber, ColorGroup::Red],
            ),
            'lifecycle_status' => array_map(
                fn (AssetStatus $status): array => ['id' => $status->value, 'label' => $status->label()],
                AssetStatus::cases(),
            ),
            'space' => self::catalog(Space::withTrashed()->orderBy('code'), $referenced('space_id'), fn (Space $s): string => "{$s->code} · {$s->name}"),
            'responsible_user' => User::query()->orderBy('name')->get(['id', 'name', 'deactivated_at'])
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'label' => $u->isActive() ? $u->name : __('assets.filters.deactivated', ['name' => $u->name]),
                ])
                ->values()->all(),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  a catalog including its removed entries (withTrashed), already ordered
     * @param  callable(TModel): string  $label
     * @return list<array{id: int, label: string}>
     */
    private static function catalog(Builder $query, QueryBuilder $referenced, callable $label): array
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('deleted_at'))->orWhereIn($q->qualifyColumn('id'), $referenced))
            ->get()
            ->map(fn (Model $model): array => [
                'id' => (int) $model->getKey(),
                'label' => $model->getAttribute('deleted_at') === null
                    ? $label($model)
                    : __('assets.filters.removed', ['name' => $label($model)]),
            ])
            ->values()->all();
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
     * @param  array<array-key, mixed>  $filters
     * @return array<string, list<mixed>>
     */
    private static function rules(array $filters): array
    {
        $rules = ['search' => ['nullable', 'string', 'max:200']];

        foreach (self::ID_CRITERIA as $key => $table) {
            $rules += self::listRules($key, ['integer', Rule::exists($table, 'id')]);
        }

        $rules += self::listRules('condition_color', ['string', Rule::in([ColorGroup::Green->value, ColorGroup::Amber->value, ColorGroup::Red->value])]);
        $rules += self::listRules('lifecycle_status', ['string', Rule::in(array_column(AssetStatus::cases(), 'value'))]);

        $rules['model'] = ['nullable', 'array:contains,include_missing'];
        $rules['model.contains'] = ['nullable', 'string', 'max:200'];
        $rules['model.include_missing'] = ['boolean'];

        $rules['acquired_on'] = ['nullable', 'array:from,to,include_missing'];
        $rules['acquired_on.from'] = ['nullable', 'date_format:Y-m-d'];
        $rules['acquired_on.to'] = ['nullable', 'date_format:Y-m-d'];
        $rules['acquired_on.include_missing'] = ['boolean'];

        if (filled(Arr::get($filters, 'acquired_on.from')) && filled(Arr::get($filters, 'acquired_on.to'))) {
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
            "{$key}.values" => ['nullable', 'array', 'list'],
            "{$key}.values.*" => $valueRules,
            ...($missing ? ["{$key}.include_missing" => ['boolean']] : []),
        ];
    }

    /**
     * The same short Spanish messages on the inventory page and in the report's filter panel.
     *
     * @return array<string, string>
     */
    private static function messages(): array
    {
        return [
            'array' => __('assets.filters.errors.shape'),
            'list' => __('assets.filters.errors.shape'),
            'string' => __('assets.filters.errors.text'),
            'max' => __('assets.filters.errors.text'),
            'integer' => __('assets.filters.errors.value'),
            'exists' => __('assets.filters.errors.value'),
            'in' => __('assets.filters.errors.value'),
            'boolean' => __('assets.filters.errors.boolean'),
            'date_format' => __('assets.filters.errors.date'),
            'after_or_equal' => __('assets.filters.errors.range'),
        ];
    }
}
