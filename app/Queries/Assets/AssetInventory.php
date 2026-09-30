<?php

namespace App\Queries\Assets;

use App\Models\Reporting\AssetRow;
use App\Queries\TextSearch;
use Illuminate\Database\Eloquent\Builder;

/**
 * The asset inventory as every list, report and statistic sees it (reporting.assets, ADR-0010). The inventory page
 * (RF_07 search, RF_08 filters) and the inventory report (RF_63) both call it, so a search or a filter always means
 * the same thing in both. Owner: assets (@DavidZamoraB); reports (@AleQuesada2012) reuse it.
 *
 *     AssetInventory::query(['search' => 'epson', 'space' => ['values' => [3]]])->paginate(15);
 *
 * InventoryFilters owns search, brand, model, condition, condition_color, lifecycle_status, space,
 * responsible_user and acquired_on. The internal space_id argument remains for the RF_35 asset list.
 */
final class AssetInventory
{
    /**
     * @param  array<string, mixed>  $filters  the same keys in the page, the report and report_generations.filters
     * @return Builder<AssetRow>
     */
    public static function query(array $filters = []): Builder
    {
        $spaceId = $filters['space_id'] ?? null;
        unset($filters['space_id']);
        $filters = InventoryFilters::validate($filters);
        $query = AssetRow::query()->when($spaceId !== null, fn (Builder $query) => $query->where('space_id', $spaceId));

        if (isset($filters['search'])) {
            $term = $filters['search'];
            $pattern = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $matches) => TextSearch::apply($matches, 'assets', $term)
                ->orWhereRaw('lower(immutable_unaccent(brand)) LIKE lower(immutable_unaccent(?))', [$pattern]));
        }
        $columns = array_map(fn (array $catalog): string => $catalog['column'], InventoryFilters::CATALOGS)
            + ['condition_color' => 'condition_color', 'lifecycle_status' => 'status', 'model' => 'model', 'acquired_on' => 'acquired_on'];
        foreach ($columns as $key => $column) {
            if (! isset($filters[$key])) {
                continue;
            }
            $criterion = $filters[$key];
            $query->where(function (Builder $matches) use ($criterion, $column): void {
                if (isset($criterion['values'])) {
                    $matches->whereIn($column, $criterion['values']);
                }
                if (isset($criterion['contains'])) {
                    $pattern = '%'.addcslashes($criterion['contains'], '%_\\').'%';
                    $matches->whereRaw("lower(immutable_unaccent({$column})) LIKE lower(immutable_unaccent(?))", [$pattern]);
                }
                if (isset($criterion['from'])) {
                    $matches->where($column, '>=', $criterion['from']);
                }
                if (isset($criterion['to'])) {
                    $matches->where($column, '<=', $criterion['to']);
                }
                if ($criterion['include_missing'] ?? false) {
                    $matches->orWhereNull($column);
                }
            });
        }

        return $query->orderBy('id');
    }
}
