<?php

namespace App\Queries\Assets;

use App\Enums\MaintenanceStatus;
use App\Models\Reporting\MaintenanceRow;
use Illuminate\Database\Eloquent\Builder;

/**
 * RF_27 · The scheduled maintenance of every asset still registered, the soonest (or most overdue) first: reporting.maintenance
 * rows still `scheduled`, with the asset's name, lifecycle status and space from reporting.assets (deleted assets are
 * left out there). The page pages::assets.maintenance lists it; managing a maintenance happens on its asset's card.
 *
 * Filter `when`: `all` (default), `overdue` (planned day before today), `week` or `month` (from today through the
 * next 7 or 30 days).
 *
 *     ScheduledMaintenance::query('overdue')->paginate(25);
 */
final class ScheduledMaintenance
{
    public const array WHEN = ['all', 'overdue', 'week', 'month'];

    /**
     * @return Builder<MaintenanceRow>
     */
    public static function query(string $when = 'all'): Builder
    {
        $today = today();

        return MaintenanceRow::query()
            ->join('reporting.assets as asset', 'asset.id', '=', 'reporting.maintenance.asset_id')
            ->select('reporting.maintenance.*')
            ->addSelect([
                'asset.display_name as asset_display_name',
                'asset.status as asset_status',
                'asset.space_code as asset_space_code',
                'asset.space_name as asset_space_name',
            ])
            ->where('reporting.maintenance.status', MaintenanceStatus::Scheduled->value)
            ->when($when === 'overdue', fn (Builder $query) => $query->whereDate('reporting.maintenance.scheduled_for', '<', $today))
            ->when(in_array($when, ['week', 'month'], true), fn (Builder $query) => $query->whereBetween('reporting.maintenance.scheduled_for', [
                $today->toDateString(),
                $today->copy()->addDays($when === 'week' ? 7 : 30)->toDateString(),
            ]))
            ->orderBy('reporting.maintenance.scheduled_for')
            ->orderBy('reporting.maintenance.id');
    }
}
