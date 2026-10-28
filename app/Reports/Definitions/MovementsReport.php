<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\AssetMovementRow;
use App\Queries\Reports\MovementLog;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** RF_67: each asset's movements as one chronological block. */
final class MovementsReport extends ReportDefinition
{
    public function code(): string
    {
        return 'movements';
    }

    public function columns(): array
    {
        return [
            'asset_code' => __('reports.columns.code'), 'asset_display_name' => __('reports.columns.asset'),
            'moved_at' => __('reports.movements.moved_at'), 'type' => __('reports.movements.type'),
            'from_space' => __('reports.movements.from_space'), 'to_space' => __('reports.movements.to_space'),
            'moved_by_name' => __('reports.movements.moved_by'), 'reason' => __('reports.movements.reason'),
        ];
    }

    /** @return Builder<AssetMovementRow> */
    public function query(array $filters): Builder
    {
        return MovementLog::query($filters);
    }

    public function filterKeys(): array
    {
        return ['asset', 'type', 'moved_at'];
    }

    public function normalizeFilters(array $filters): array
    {
        return MovementLog::validate($filters);
    }

    public function filterFields(): array
    {
        $choices = MovementLog::choices();

        return [
            'asset' => ['type' => 'values', 'label' => __('reports.filters.labels.asset'), 'options' => $choices['asset']],
            'type' => ['type' => 'values', 'label' => __('reports.movements.type'), 'options' => $choices['type']],
            'moved_at' => ['type' => 'date', 'label' => __('reports.movements.moved_at')],
        ];
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        return match ($key) {
            'moved_at' => $row->getAttribute('moved_at')->timezone('America/Costa_Rica')->format('d/m/Y H:i'),
            'from_space', 'to_space' => $row->getAttribute($key.'_code') === null ? null
                : $row->getAttribute($key.'_code').' · '.$row->getAttribute($key.'_name'),
            default => parent::cell($row, $key),
        };
    }
}
