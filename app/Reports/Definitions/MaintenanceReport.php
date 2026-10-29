<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\MaintenanceRow;
use App\Queries\Reports\MaintenanceLog;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** RF_66: completed maintenance and repairs, told apart by their kind. */
final class MaintenanceReport extends ReportDefinition
{
    public function code(): string
    {
        return 'maintenance';
    }

    public function columns(): array
    {
        return [
            'asset_code' => __('reports.columns.code'), 'asset_display_name' => __('reports.columns.asset'),
            'completed_on' => __('reports.maintenance.completed_on'), 'kind' => __('reports.maintenance.kind'),
            'description' => __('reports.maintenance.description'), 'performed_by' => __('reports.maintenance.performed_by'),
            'registered_by_name' => __('reports.columns.registered_by'),
        ];
    }

    /** @return Builder<MaintenanceRow> */
    public function query(array $filters): Builder
    {
        return MaintenanceLog::query($filters);
    }

    public function filterKeys(): array
    {
        return ['asset', 'kind', 'completed_on'];
    }

    public function normalizeFilters(array $filters): array
    {
        return MaintenanceLog::validate($filters);
    }

    public function filterFields(): array
    {
        $choices = MaintenanceLog::choices();

        return [
            'asset' => ['type' => 'values', 'label' => __('reports.filters.labels.asset'), 'options' => $choices['asset']],
            'kind' => ['type' => 'values', 'label' => __('reports.maintenance.kind'), 'options' => $choices['kind']],
            'completed_on' => ['type' => 'date', 'label' => __('reports.maintenance.completed_on')],
        ];
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        return $key === 'kind' ? __('reports.maintenance.kinds.'.$row->getAttribute('kind')->value) : parent::cell($row, $key);
    }
}
