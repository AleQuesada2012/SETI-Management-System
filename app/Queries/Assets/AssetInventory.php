<?php

namespace App\Queries\Assets;

use App\Models\Reporting\AssetRow;
use Illuminate\Database\Eloquent\Builder;

/**
 * The asset inventory as every list, report and statistic sees it (reporting.assets, ADR-0010). The inventory page
 * (RF_07 search, RF_08 filters) and the inventory report (RF_63) both call it, so a search or a filter always means
 * the same thing in both. Owner: assets (@DavidZamoraB); reports (@AleQuesada2012) reuse it.
 *
 *     AssetInventory::query(['search' => 'epson', 'space_id' => 3])->paginate(15);
 */
final class AssetInventory
{
    /**
     * @param  array<string, mixed>  $filters  the same keys in the page, the report and report_generations.filters
     * @return Builder<AssetRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = AssetRow::query();

        // TODO(RF_07, @DavidZamoraB): TextSearch::apply($query, 'assets', $filters['search'] ?? null), plus brand and
        //   type names through reporting.assets columns.
        // TODO(RF_08, @DavidZamoraB): the mockup filters: brand, condition/status, space, acquisition date range.

        return $query->orderBy('id');
    }
}
