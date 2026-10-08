<?php

namespace App\Queries\Assets;

use App\Models\Reporting\AssetRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

/**
 * The asset inventory as every list, report and statistic sees it (reporting.assets, ADR-0010). The inventory page
 * (RF_07 search, RF_08 filters, RF_30 by custodian) and the inventory report (RF_63) both call it, so a search or a
 * filter always means the same thing in both. Owner: assets (@DavidZamoraB); reports (@AleQuesada2012) reuse it.
 *
 * The filters follow the inventory contract in InventoryFilters (keys, shapes, validation, choices): criteria combine
 * with AND, the alternatives inside one criterion with OR, and `include_missing` adds "no value" as one more
 * alternative. Invalid filters throw a ValidationException before any query runs.
 *
 *     AssetInventory::query([
 *         'search' => 'lenovo',
 *         'space' => ['values' => [3], 'include_missing' => true],   // Lab 3 OR without a space
 *         'acquired_on' => ['from' => '2026-09-01', 'to' => '2026-09-30'],
 *     ])->paginate(15);
 */
final class AssetInventory
{
    /** Criterion => reporting.assets column holding its id or code. */
    private const COLUMNS = [
        'brand' => 'brand_id',
        'category' => 'category_id',
        'condition' => 'condition_id',
        'condition_color' => 'condition_color',
        'lifecycle_status' => 'status',
        'space' => 'space_id',
        'responsible_user' => 'responsible_user_id',
    ];

    /**
     * @param  array<array-key, mixed>  $filters  raw or normalized contract input; the page, the report and
     *                                            report_generations.filters all use the same keys
     * @return Builder<AssetRow>
     */
    public static function query(array $filters = []): Builder
    {
        $filters = InventoryFilters::validate($filters);
        $query = AssetRow::query();

        if (isset($filters['search'])) {
            self::search($query, $filters['search']);
        }

        foreach (self::COLUMNS as $key => $column) {
            if (isset($filters[$key])) {
                self::oneOf($query, $column, $filters[$key]['values'] ?? [], $filters[$key]['include_missing'] ?? false);
            }
        }

        if (isset($filters['model'])) {
            $model = $filters['model'];
            $query->where(function (Builder $q) use ($model): void {
                if (isset($model['contains'])) {
                    $q->orWhereRaw('lower(immutable_unaccent(model)) LIKE lower(immutable_unaccent(?))', [self::pattern($model['contains'])]);
                }
                if ($model['include_missing'] ?? false) {
                    $q->orWhereNull('model');
                }
            });
        }

        if (isset($filters['acquired_on'])) {
            $range = $filters['acquired_on'];
            $query->where(function (Builder $q) use ($range): void {
                if (isset($range['from']) || isset($range['to'])) {
                    $q->orWhere(function (Builder $dates) use ($range): void {
                        if (isset($range['from'])) {
                            $dates->where('acquired_on', '>=', $range['from']);
                        }
                        if (isset($range['to'])) {
                            $dates->where('acquired_on', '<=', $range['to']);
                        }
                    });
                }
                if ($range['include_missing'] ?? false) {
                    $q->orWhereNull('acquired_on');
                }
            });
        }

        return $query->orderBy('id');
    }

    /**
     * RF_07: partial, case- and accent-insensitive match on name, model, code, tag and serial (assets.search_text,
     * with its trigram index) or on the brand's name.
     *
     * @param  Builder<AssetRow>  $query
     */
    private static function search(Builder $query, string $term): void
    {
        $pattern = self::pattern($term);

        $query->where(fn (Builder $q) => $q
            ->whereIn('id', fn (QueryBuilder $sub) => $sub->select('id')->from('assets')
                ->whereRaw('search_text LIKE lower(immutable_unaccent(?))', [$pattern]))
            ->orWhereRaw('lower(immutable_unaccent(brand)) LIKE lower(immutable_unaccent(?))', [$pattern]));
    }

    /**
     * @param  Builder<AssetRow>  $query
     * @param  list<int|string>  $values
     */
    private static function oneOf(Builder $query, string $column, array $values, bool $includeMissing): void
    {
        $query->where(function (Builder $q) use ($column, $values, $includeMissing): void {
            if ($values !== []) {
                $q->orWhereIn($column, $values);
            }
            if ($includeMissing) {
                $q->orWhereNull($column);
            }
        });
    }

    /** A LIKE pattern for a partial match that treats %, _ and backslash literally (as TextSearch does). */
    private static function pattern(string $term): string
    {
        return '%'.addcslashes(Str::lower(trim($term)), '%_\\').'%';
    }
}
