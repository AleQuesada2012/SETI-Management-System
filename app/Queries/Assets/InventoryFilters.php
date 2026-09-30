<?php

namespace App\Queries\Assets;

use App\Enums\AssetStatus;
use App\Models\AssetCondition;
use App\Models\Brand;
use App\Models\Reporting\AssetRow;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

/** Shared inventory filter contract for lists and RF_63; missing values are explicit OR alternatives. */
final class InventoryFilters
{
    /** @var array<string, array{column: string, table: string, nullable: bool}> */
    public const CATALOGS = [
        'brand' => ['column' => 'brand_id', 'table' => 'brands', 'nullable' => true],
        'condition' => ['column' => 'condition_id', 'table' => 'asset_conditions', 'nullable' => false],
        'space' => ['column' => 'space_id', 'table' => 'spaces', 'nullable' => true],
        'responsible_user' => ['column' => 'responsible_user_id', 'table' => 'users', 'nullable' => true],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return ['search', 'brand', 'model', 'condition', 'condition_color', 'lifecycle_status', 'space', 'responsible_user', 'acquired_on'];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        $rules = ['search' => ['nullable', 'string', 'max:200']];
        foreach (self::CATALOGS as $key => $catalog) {
            $rules[$key] = ['nullable', 'array:values'.($catalog['nullable'] ? ',include_missing' : '')];
            $rules["{$key}.values"] = ['nullable', 'array', 'list'];
            $rules["{$key}.values.*"] = ['integer', Rule::exists($catalog['table'], 'id')];
            if ($catalog['nullable']) {
                $rules["{$key}.include_missing"] = ['boolean'];
            }
        }
        foreach (['condition_color' => ['green', 'amber', 'red'], 'lifecycle_status' => array_column(AssetStatus::cases(), 'value')] as $key => $values) {
            $rules[$key] = ['nullable', 'array:values'];
            $rules["{$key}.values"] = ['nullable', 'array', 'list'];
            $rules["{$key}.values.*"] = ['string', Rule::in($values)];
        }
        $rules['model'] = ['nullable', 'array:contains,include_missing'];
        $rules['model.contains'] = ['nullable', 'string', 'max:200'];
        $rules['model.include_missing'] = ['boolean'];
        $rules['acquired_on'] = ['nullable', 'array:from,to,include_missing'];
        $rules['acquired_on.from'] = ['nullable', 'date_format:Y-m-d'];
        $rules['acquired_on.to'] = ['nullable', 'date_format:Y-m-d'];
        $rules['acquired_on.include_missing'] = ['boolean'];

        $validator = Validator::make($filters, $rules, [
            'array' => __('reports.filters.errors.shape'),
            'list' => __('reports.filters.errors.shape'),
            'string' => __('reports.filters.errors.text'),
            'max' => __('reports.filters.errors.text'),
            'integer' => __('reports.filters.errors.value'),
            'exists' => __('reports.filters.errors.value'),
            'in' => __('reports.filters.errors.value'),
            'boolean' => __('reports.filters.errors.boolean'),
            'date_format' => __('reports.filters.errors.date'),
        ])->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (array_diff(array_keys($filters), self::keys()) as $key) {
                $validator->errors()->add((string) $key, __('reports.filters.errors.unknown', ['key' => $key]));
            }
            $range = $filters['acquired_on'] ?? [];
            if (is_array($range) && is_string($range['from'] ?? null) && is_string($range['to'] ?? null)
                && $range['from'] !== '' && $range['to'] !== '' && $range['from'] > $range['to']) {
                $validator->errors()->add('acquired_on.to', __('reports.filters.errors.range'));
            }
        });
        $validator->validate();

        $normalized = [];
        if (trim($filters['search'] ?? '') !== '') {
            $normalized['search'] = trim($filters['search']);
        }
        foreach (array_diff(self::keys(), ['search']) as $key) {
            $criterion = $filters[$key] ?? [];
            $selection = [];
            if (! empty($criterion['values'])) {
                $values = isset(self::CATALOGS[$key]) ? array_map(intval(...), $criterion['values']) : $criterion['values'];
                $values = array_values(array_unique($values));
                sort($values);
                $selection['values'] = $values;
            }
            foreach (['contains', 'from', 'to'] as $member) {
                if (trim($criterion[$member] ?? '') !== '') {
                    $selection[$member] = trim($criterion[$member]);
                }
            }
            if (! empty($criterion['include_missing'])) {
                $selection['include_missing'] = true;
            }
            if ($selection !== []) {
                $normalized[$key] = $selection;
            }
        }

        return $normalized;
    }

    /** Stable choices from the full inventory, including historical references.
     * @return array<string, list<array{id: int|string, label: string}>>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (['brand' => Brand::class, 'condition' => AssetCondition::class, 'space' => Space::class] as $key => $model) {
            $choices[$key] = $model::withTrashed()
                ->where(fn (Builder $query) => $query->whereNull('deleted_at')
                    ->orWhereIn('id', AssetRow::query()->select(self::CATALOGS[$key]['column'])))
                ->orderBy('name')->get()->map(fn ($entry): array => [
                    'id' => $entry->id,
                    'label' => $entry->trashed() ? __('reports.filters.removed', ['name' => $entry->name]) : $entry->name,
                ])->all();
        }
        $choices['responsible_user'] = User::query()->orderBy('name')->get()->map(fn (User $user): array => [
            'id' => $user->id,
            'label' => $user->deactivated_at ? __('reports.filters.deactivated', ['name' => $user->name]) : $user->name,
        ])->all();
        $choices['condition_color'] = array_map(fn (string $color): array => ['id' => $color, 'label' => __('reports.filters.colors.'.$color)], ['green', 'amber', 'red']);
        $choices['lifecycle_status'] = array_map(fn (AssetStatus $status): array => ['id' => $status->value, 'label' => $status->label()], AssetStatus::cases());

        return $choices;
    }
}
