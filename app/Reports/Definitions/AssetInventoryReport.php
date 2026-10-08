<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\AssetRow;
use App\Queries\Assets\AssetInventory;
use App\Queries\Assets\InventoryFilters;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;

/** RF_63: the inventory, with physical condition separate from lifecycle status. */
final class AssetInventoryReport extends ReportDefinition
{
    public function code(): string
    {
        return 'assets_inventory';
    }

    public function columns(): array
    {
        $columns = [];
        foreach (['code', 'asset_tag', 'display_name', 'brand', 'model', 'condition', 'status', 'space_name', 'responsible_name', 'acquired_on'] as $key) {
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
        return InventoryFilters::keys();
    }

    public function normalizeFilters(array $filters): array
    {
        return InventoryFilters::validate($filters);
    }

    public function filterFields(): array
    {
        $choices = InventoryFilters::choices();
        $fields = ['search' => ['label' => __('reports.filters.labels.search'), 'type' => 'text']];
        foreach (array_diff(InventoryFilters::keys(), ['search']) as $key) {
            $fields[$key] = [
                'label' => __('reports.filters.labels.'.$key),
                'type' => match ($key) {
                    'model' => 'contains', 'acquired_on' => 'date', default => 'values'
                },
                'options' => $choices[$key] ?? [],
            ];
            if (in_array($key, ['brand', 'model', 'space', 'responsible_user', 'acquired_on'], true)) {
                $fields[$key]['missing_label'] = __('reports.filters.missing.'.$key);
            }
        }

        return $fields;
    }
}
