<?php

namespace App\Queries\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * The shared filter contract (.ai/rules/reports.md) for reports that combine `values` selections with inclusive
 * date ranges (RF_66, RF_67, RF_76, RF_77, RF_82, RF_83). A schema maps each key to the rules of one selected value,
 * or to 'date' for a `{from, to}` range:
 *
 *     ReportFilters::validate($filters, ['asset' => ['integer', Rule::exists('assets', 'id')], 'moved_at' => 'date']);
 */
final class ReportFilters
{
    /**
     * Reject invalid shapes, values, dates, inverted ranges and unknown keys with the shared Spanish messages, then
     * remove empty restrictions and duplicates. `[]` means every record.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, list<mixed>|'date'>  $schema
     * @return array<string, mixed>
     */
    public static function validate(array $filters, array $schema): array
    {
        $rules = [];
        $messages = [];
        foreach ($schema as $key => $valueRules) {
            if ($valueRules === 'date') {
                $rules += [$key => ['nullable', 'array:from,to'], "$key.from" => ['nullable', 'date_format:Y-m-d'], "$key.to" => ['nullable', 'date_format:Y-m-d']];
                $messages += ["$key.array" => __('reports.filters.errors.shape'),
                    "$key.from.date_format" => __('reports.filters.errors.date'), "$key.to.date_format" => __('reports.filters.errors.date')];

                continue;
            }
            $rules += [$key => ['nullable', 'array:values'], "$key.values" => ['nullable', 'array'], "$key.values.*" => ['required', ...$valueRules]];
            $messages += ["$key.array" => __('reports.filters.errors.shape'), "$key.values.array" => __('reports.filters.errors.shape'),
                "$key.values.*" => __('reports.filters.errors.value')];
        }

        Validator::make($filters, $rules, $messages)->after(function (ValidatorInstance $validator) use ($filters, $schema): void {
            foreach (array_diff(array_keys($filters), array_keys($schema)) as $key) {
                $validator->errors()->add((string) $key, __('reports.filters.errors.unknown', ['key' => $key]));
            }
            foreach ($schema as $key => $valueRules) {
                $criterion = $filters[$key] ?? null;
                if ($criterion !== null && ! is_array($criterion)) {
                    $validator->errors()->add($key, __('reports.filters.errors.shape'));
                } elseif ($valueRules !== 'date' && isset($criterion['values']) && ! is_array($criterion['values'])) {
                    $validator->errors()->add("$key.values", __('reports.filters.errors.shape'));
                } elseif ($valueRules === 'date' && ! $validator->errors()->has("$key.*")
                    && ! empty($criterion['from']) && ! empty($criterion['to']) && $criterion['from'] > $criterion['to']) {
                    $validator->errors()->add("$key.to", __('reports.filters.errors.range'));
                }
            }
        })->validate();

        $normalized = [];
        foreach ($schema as $key => $valueRules) {
            if ($valueRules === 'date') {
                $dates = array_filter($filters[$key] ?? [], fn (mixed $value): bool => is_string($value) && trim($value) !== '');
                if ($dates !== []) {
                    $normalized[$key] = $dates;
                }

                continue;
            }
            $values = $filters[$key]['values'] ?? [];
            if (in_array('integer', $valueRules, true)) {
                $values = array_map(intval(...), $values);
            }
            $values = array_values(array_unique($values));
            sort($values);
            if ($values !== []) {
                $normalized[$key] = ['values' => $values];
            }
        }

        return $normalized;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters  normalized
     */
    public static function whereValues(Builder $query, array $filters, string $key, string $column): void
    {
        if (isset($filters[$key])) {
            $query->whereIn($column, $filters[$key]['values']);
        }
    }

    /**
     * Inclusive bounds on a calendar-date column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters  normalized
     */
    public static function whereDates(Builder $query, array $filters, string $key, string $column): void
    {
        if (isset($filters[$key]['from'])) {
            $query->where($column, '>=', $filters[$key]['from']);
        }
        if (isset($filters[$key]['to'])) {
            $query->where($column, '<=', $filters[$key]['to']);
        }
    }

    /**
     * Inclusive Costa Rica calendar days on a timestamptz column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters  normalized
     */
    public static function whereLocalDays(Builder $query, array $filters, string $key, string $column): void
    {
        foreach (['from', 'to'] as $bound) {
            if (isset($filters[$key][$bound])) {
                $day = CarbonImmutable::parse($filters[$key][$bound], 'America/Costa_Rica')->startOfDay();
                $query->where($column, $bound === 'from' ? '>=' : '<', ($bound === 'to' ? $day->addDay() : $day)->toIso8601String());
            }
        }
    }

    /**
     * Add each row's asset as the inventory names it (`asset_display_name`, falling back to the view's own name or
     * code once the asset was deleted) and its current lifecycle status (`asset_status`). Qualify the view's columns
     * afterwards: the joined reporting.assets shares some of their names.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function withCurrentAsset(Builder $query): Builder
    {
        $view = $query->getModel()->getTable();

        return $query->select("$view.*")
            ->selectRaw(self::displayName($view).' AS asset_display_name')
            ->selectRaw('current_asset.status AS asset_status')
            ->leftJoin('reporting.assets AS current_asset', 'current_asset.id', '=', "$view.asset_id");
    }

    /**
     * The assets present in a report's full dataset, as filter choices labelled "code · name".
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $dataset
     * @return list<array{id: int, label: string}>
     */
    public static function assetChoices(Builder $dataset): array
    {
        $view = $dataset->getModel()->getTable();

        return self::withCurrentAsset($dataset)->toBase()->reorder()
            ->select("$view.asset_id", "$view.asset_code")->selectRaw(self::displayName($view).' AS asset_display_name')
            ->distinct()->orderBy("$view.asset_code")->get()
            ->map(fn (object $row): array => ['id' => (int) $row->asset_id, 'label' => $row->asset_code.' · '.$row->asset_display_name])
            ->values()->all();
    }

    /** SQL for the asset's inventory name; needs the `current_asset` join of withCurrentAsset(). */
    public static function displayName(string $view): string
    {
        return "COALESCE(current_asset.display_name, $view.asset_name, $view.asset_code)";
    }
}
