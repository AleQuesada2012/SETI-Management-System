<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\AssetRow;
use App\Queries\Reports\Warranties;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** RF_75: the supplier is the warranty provider, as defined in Agent-DB §7. */
final class WarrantiesReport extends ReportDefinition
{
    public function code(): string
    {
        return 'warranties';
    }

    public function columns(): array
    {
        return [
            'code' => __('reports.columns.code'), 'display_name' => __('reports.columns.display_name'),
            'supplier' => __('reports.warranties.supplier'),
            'warranty_starts_on' => __('reports.warranties.starts_on'),
            'warranty_ends_on' => __('reports.warranties.ends_on'), 'warranty_state' => __('reports.warranties.state'),
        ];
    }

    /** @return Builder<AssetRow> */
    public function query(array $filters): Builder
    {
        return Warranties::query($filters);
    }

    public function filterKeys(): array
    {
        return ['warranty_state'];
    }

    public function normalizeFilters(array $filters): array
    {
        return Warranties::validate($filters);
    }

    public function filterFields(): array
    {
        return ['warranty_state' => ['type' => 'values', 'label' => __('reports.warranties.state'),
            'options' => array_map(fn (string $state): array => ['id' => $state, 'label' => __('reports.warranties.states.'.$state)], Warranties::STATES)]];
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        return match ($key) {
            'warranty_state' => __('reports.warranties.states.'.$row->getAttribute($key)),
            'supplier' => $row->getAttribute($key) ?? __('reports.warranties.no_supplier'),
            default => parent::cell($row, $key),
        };
    }
}
