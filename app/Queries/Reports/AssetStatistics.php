<?php

namespace App\Queries\Reports;

use App\Enums\AssetStatus;
use App\Models\Reporting\AssetRow;
use App\Queries\Assets\InventoryFilters;

/** RF_80/RF_81: aggregate current reporting rows, without stored counters. */
final class AssetStatistics
{
    /** @return array<string, list<array{id: int|string, label: string, count: int, color?: int}>> */
    public static function current(): array
    {
        $choices = InventoryFilters::choices();
        $series = [];
        $conditionColors = AssetRow::query()->select('condition_id', 'condition_color')->distinct()->get()
            ->mapWithKeys(fn (AssetRow $row): array => [$row->getAttribute('condition_id') => match ($row->condition_color->value) {
                'green' => 2, 'amber' => 3, 'red' => 4, 'gray' => 5,
            }]);
        foreach (['condition' => 'condition_id', 'category' => 'category_id'] as $key => $column) {
            $counts = AssetRow::query()->selectRaw($column.', count(*) AS total')->groupBy($column)->pluck('total', $column);
            $series[$key] = array_map(fn (array $choice): array => [
                'id' => $choice['id'], 'label' => $choice['label'], 'count' => (int) ($counts[$choice['id']] ?? 0),
                ...($key === 'condition' ? ['color' => $conditionColors[$choice['id']] ?? 5] : []),
            ], $choices[$key]);
        }
        $counts = AssetRow::query()->selectRaw('status, count(*) AS total')->groupBy('status')->pluck('total', 'status');
        $series['lifecycle'] = array_map(fn (AssetStatus $status): array => [
            'id' => $status->value, 'label' => $status->label(), 'count' => (int) ($counts[$status->value] ?? 0),
        ], AssetStatus::cases());

        return $series;
    }
}
