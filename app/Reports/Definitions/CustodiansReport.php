<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\AssetRow;
use App\Queries\Assets\AssetInventory;
use App\Queries\Assets\InventoryFilters;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** RF_78: current asset custodians, including unassigned assets. */
final class CustodiansReport extends ReportDefinition
{
    public function code(): string
    {
        return 'custodians';
    }

    public function columns(): array
    {
        $columns = [];
        foreach (['code', 'asset_tag', 'display_name', 'responsible_name', 'responsible_email', 'space_name'] as $key) {
            $columns[$key] = __('reports.columns.'.$key);
        }

        return $columns;
    }

    /** @return Builder<AssetRow> */
    public function query(array $filters): Builder
    {
        return AssetInventory::query($filters);
    }

    public function filterKeys(): array
    {
        return ['responsible_user'];
    }

    public function normalizeFilters(array $filters): array
    {
        parent::normalizeFilters($filters);

        return InventoryFilters::validate($filters);
    }

    public function filterFields(): array
    {
        return ['responsible_user' => ['type' => 'values', 'label' => __('reports.filters.labels.responsible_user'),
            'options' => InventoryFilters::choices()['responsible_user'],
            'missing_label' => __('reports.filters.missing.responsible_user')]];
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        return $key === 'responsible_name' && $row->getAttribute($key) === null
            ? __('reports.filters.missing.responsible_user') : parent::cell($row, $key);
    }
}
