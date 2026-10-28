<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\AssetEventRow;
use App\Queries\Reports\AssetDisposals;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;

/** RF_76: decommissions and losses, with the asset's current status so later recoveries are visible. */
final class DisposalsReport extends ReportDefinition
{
    public function code(): string
    {
        return 'disposals';
    }

    public function columns(): array
    {
        return [
            'asset_code' => __('reports.columns.code'), 'asset_display_name' => __('reports.columns.asset'),
            'kind' => __('reports.disposals.kind'), 'occurred_on' => __('reports.disposals.occurred_on'),
            'description' => __('reports.disposals.description'), 'registered_by_name' => __('reports.columns.registered_by'),
            'asset_status' => __('reports.disposals.current_status'),
        ];
    }

    /** @return Builder<AssetEventRow> */
    public function query(array $filters): Builder
    {
        return AssetDisposals::query($filters);
    }

    public function filterKeys(): array
    {
        return ['kind', 'occurred_on'];
    }

    public function normalizeFilters(array $filters): array
    {
        return AssetDisposals::validate($filters);
    }

    public function filterFields(): array
    {
        return [
            'kind' => ['type' => 'values', 'label' => __('reports.disposals.kind'), 'options' => AssetDisposals::choices()['kind']],
            'occurred_on' => ['type' => 'date', 'label' => __('reports.disposals.occurred_on')],
        ];
    }
}
