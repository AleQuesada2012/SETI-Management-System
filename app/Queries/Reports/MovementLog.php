<?php

namespace App\Queries\Reports;

use App\Enums\MovementType;
use App\Models\Reporting\AssetMovementRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * RF_67: inventory movements grouped by asset and in chronological order, so each asset's history reads as one
 * block. Movement times are filtered by Costa Rica calendar days.
 */
final class MovementLog
{
    /**
     * @param  array<string, mixed>  $filters  normalized by validate()
     * @return Builder<AssetMovementRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = ReportFilters::withCurrentAsset(AssetMovementRow::query());
        ReportFilters::whereValues($query, $filters, 'asset', 'reporting.asset_movements.asset_id');
        ReportFilters::whereValues($query, $filters, 'type', 'reporting.asset_movements.type');
        ReportFilters::whereLocalDays($query, $filters, 'moved_at', 'reporting.asset_movements.moved_at');

        return $query->orderBy('reporting.asset_movements.asset_code')->orderBy('reporting.asset_movements.asset_id')
            ->orderBy('reporting.asset_movements.moved_at')->orderBy('reporting.asset_movements.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        return ReportFilters::validate($filters, [
            'asset' => ['integer', Rule::exists('assets', 'id')],
            'type' => ['string', Rule::enum(MovementType::class)],
            'moved_at' => 'date',
        ]);
    }

    /** @return array<string, list<array{id: int|string, label: string}>> */
    public static function choices(): array
    {
        return [
            'asset' => ReportFilters::assetChoices(AssetMovementRow::query()),
            'type' => array_map(fn (MovementType $type): array => ['id' => $type->value, 'label' => $type->label()], MovementType::cases()),
        ];
    }
}
