<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\AssetComponentRow;
use App\Queries\Reports\ComponentList;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;

/** RF_77: every registered component next to the asset it belongs to. */
final class ComponentsReport extends ReportDefinition
{
    public function code(): string
    {
        return 'components';
    }

    public function columns(): array
    {
        return [
            'asset_code' => __('reports.columns.code'), 'asset_tag' => __('reports.columns.asset_tag'),
            'asset_display_name' => __('reports.columns.asset'), 'name' => __('reports.components.name'),
            'specs' => __('reports.components.specs'), 'serial_number' => __('reports.components.serial_number'),
            'quantity' => __('reports.components.quantity'), 'notes' => __('reports.components.notes'),
        ];
    }

    /** @return Builder<AssetComponentRow> */
    public function query(array $filters): Builder
    {
        return ComponentList::query($filters);
    }

    public function filterKeys(): array
    {
        return ['asset'];
    }

    public function normalizeFilters(array $filters): array
    {
        return ComponentList::validate($filters);
    }

    public function filterFields(): array
    {
        return ['asset' => ['type' => 'values', 'label' => __('reports.filters.labels.asset'), 'options' => ComponentList::choices()['asset']]];
    }
}
