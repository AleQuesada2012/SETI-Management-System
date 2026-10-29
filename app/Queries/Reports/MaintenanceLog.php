<?php

namespace App\Queries\Reports;

use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Models\Reporting\MaintenanceRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * RF_66: the maintenance and repairs performed (completed records), dated by their completion day. A repair is
 * corrective maintenance (MaintenanceKind), so the report shows preventive work as "Mantenimiento" and corrective
 * work as "Reparación". RF_82 counts the same records.
 */
final class MaintenanceLog
{
    /**
     * @param  array<string, mixed>  $filters  normalized by validate()
     * @return Builder<MaintenanceRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = ReportFilters::withCurrentAsset(self::performed());
        ReportFilters::whereValues($query, $filters, 'asset', 'reporting.maintenance.asset_id');
        ReportFilters::whereValues($query, $filters, 'kind', 'reporting.maintenance.kind');
        ReportFilters::whereDates($query, $filters, 'completed_on', 'reporting.maintenance.completed_on');

        return $query->orderByDesc('reporting.maintenance.completed_on')->orderByDesc('reporting.maintenance.id');
    }

    /** @return Builder<MaintenanceRow> */
    public static function performed(): Builder
    {
        return MaintenanceRow::query()->where('reporting.maintenance.status', MaintenanceStatus::Completed->value);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        return ReportFilters::validate($filters, [
            'asset' => ['integer', Rule::exists('assets', 'id')],
            'kind' => ['string', Rule::enum(MaintenanceKind::class)],
            'completed_on' => 'date',
        ]);
    }

    /** @return array<string, list<array{id: int|string, label: string}>> */
    public static function choices(): array
    {
        return [
            'asset' => ReportFilters::assetChoices(self::performed()),
            'kind' => array_map(fn (MaintenanceKind $kind): array => ['id' => $kind->value, 'label' => __('reports.maintenance.kinds.'.$kind->value)], MaintenanceKind::cases()),
        ];
    }
}
