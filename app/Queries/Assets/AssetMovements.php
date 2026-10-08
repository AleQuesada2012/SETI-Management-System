<?php

namespace App\Queries\Assets;

use App\Models\Asset;
use App\Models\Reporting\AssetMovementRow;
use Illuminate\Database\Eloquent\Builder;

/**
 * The movements of the inventory as lists and reports see them (reporting.asset_movements, ADR-0010): RF_25 shows an
 * asset's movements on its detail page; RF_09 (asset history) and RF_67 (movements report) build on this Query.
 * Owner: assets (@DavidZamoraB).
 *
 *     AssetMovements::forAsset($asset)->get();   // newest first
 */
final class AssetMovements
{
    /**
     * One asset's entries, transfers and exits, newest first. Movements of the same instant keep their insert order.
     *
     * @return Builder<AssetMovementRow>
     */
    public static function forAsset(Asset $asset): Builder
    {
        return AssetMovementRow::query()
            ->where('asset_id', $asset->id)
            ->orderByDesc('moved_at')
            ->orderByDesc('id');
    }
}
