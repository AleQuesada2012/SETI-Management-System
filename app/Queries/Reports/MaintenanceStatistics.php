<?php

namespace App\Queries\Reports;

use App\Enums\MaintenanceKind;
use Illuminate\Support\Facades\DB;

/**
 * RF_82: counts of the maintenance and repairs performed in a period (the records of the RF_66 report), and the
 * assets with the most interventions. Computed live from reporting.maintenance, without stored counters.
 */
final class MaintenanceStatistics
{
    public const int TOP_ASSETS = 10;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        return ReportFilters::validate($filters, ['completed_on' => 'date']);
    }

    /**
     * @param  array<string, mixed>  $filters  normalized by validate()
     * @return array{total: int, kinds: list<array{id: string, label: string, count: int, color: int}>, assets: list<array{id: int, label: string, count: int, preventive: int, corrective: int}>}
     */
    public static function summary(array $filters = []): array
    {
        $performed = MaintenanceLog::performed();
        ReportFilters::whereDates($performed, $filters, 'completed_on', 'reporting.maintenance.completed_on');

        $counts = (clone $performed)->toBase()->selectRaw('kind, count(*) AS total')->groupBy('kind')->pluck('total', 'kind');
        $kinds = array_map(fn (MaintenanceKind $kind): array => [
            'id' => $kind->value, 'label' => __('reports.maintenance.kinds.'.$kind->value),
            'count' => (int) ($counts[$kind->value] ?? 0), 'color' => $kind === MaintenanceKind::Preventive ? 1 : 3,
        ], MaintenanceKind::cases());

        $name = ReportFilters::displayName('reporting.maintenance');
        $assets = ReportFilters::withCurrentAsset($performed)->toBase()->reorder()
            ->select('reporting.maintenance.asset_id', 'reporting.maintenance.asset_code')
            ->selectRaw("$name AS asset_display_name, count(*) AS total")
            ->selectRaw('count(*) FILTER (WHERE reporting.maintenance.kind = ?) AS preventive', [MaintenanceKind::Preventive->value])
            ->selectRaw('count(*) FILTER (WHERE reporting.maintenance.kind = ?) AS corrective', [MaintenanceKind::Corrective->value])
            ->groupBy('reporting.maintenance.asset_id', 'reporting.maintenance.asset_code', DB::raw($name))
            ->orderByDesc('total')->orderBy('reporting.maintenance.asset_code')->limit(self::TOP_ASSETS)->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->asset_id, 'label' => $row->asset_code.' · '.$row->asset_display_name, 'count' => (int) $row->total,
                'preventive' => (int) $row->preventive, 'corrective' => (int) $row->corrective,
            ])->values()->all();

        return ['total' => array_sum(array_column($kinds, 'count')), 'kinds' => $kinds, 'assets' => $assets];
    }
}
