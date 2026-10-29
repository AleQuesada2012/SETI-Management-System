<?php

namespace App\Queries\Reports;

use App\Models\Reporting\AssetRow;
use App\Queries\Assets\AssetInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

/** RF_75: recorded warranties; validity is computed by reporting.assets using the Costa Rica date. */
final class Warranties
{
    public const array STATES = ['valid', 'expired'];

    /**
     * @param  array<string, mixed>  $filters  normalized by validate()
     * @return Builder<AssetRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = AssetInventory::query([])->whereNotNull('warranty_ends_on');
        if (isset($filters['warranty_state'])) {
            $query->whereIn('warranty_state', $filters['warranty_state']['values']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        Validator::make($filters, [
            'warranty_state' => ['nullable', 'array:values'],
            'warranty_state.values' => ['nullable', 'array'],
            'warranty_state.values.*' => ['required', 'string', Rule::in(self::STATES)],
        ], [
            'warranty_state.array' => __('reports.filters.errors.shape'),
            'warranty_state.values.array' => __('reports.filters.errors.shape'),
            'warranty_state.values.*.required' => __('reports.filters.errors.value'),
            'warranty_state.values.*.string' => __('reports.filters.errors.value'),
            'warranty_state.values.*.in' => __('reports.filters.errors.value'),
        ])->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (array_diff(array_keys($filters), ['warranty_state']) as $key) {
                $validator->errors()->add((string) $key, __('reports.filters.errors.unknown', ['key' => $key]));
            }
            $criterion = $filters['warranty_state'] ?? null;
            if ($criterion !== null && ! is_array($criterion)) {
                $validator->errors()->add('warranty_state', __('reports.filters.errors.shape'));
            }
            if (is_array($criterion) && isset($criterion['values']) && ! is_array($criterion['values'])) {
                $validator->errors()->add('warranty_state.values', __('reports.filters.errors.shape'));
            }
        })->validate();

        $values = $filters['warranty_state']['values'] ?? [];
        $selected = array_values(array_filter(self::STATES, fn (string $state): bool => in_array($state, $values, true)));

        return $selected === [] ? [] : ['warranty_state' => ['values' => $selected]];
    }
}
