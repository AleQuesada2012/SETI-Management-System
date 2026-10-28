<?php

namespace App\Queries\Reports;

use App\Enums\AssetEventKind;
use App\Models\Reporting\AssetEventRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * RF_76: every loss and decommission event, including losses whose asset was later recovered, with the asset's
 * current lifecycle status (null once the asset was deleted as registered by mistake).
 */
final class AssetDisposals
{
    public const array KINDS = [AssetEventKind::Decommission, AssetEventKind::Loss];

    /**
     * @param  array<string, mixed>  $filters  normalized by validate()
     * @return Builder<AssetEventRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = ReportFilters::withCurrentAsset(AssetEventRow::query())
            ->whereIn('reporting.asset_events.kind', array_map(fn (AssetEventKind $kind): string => $kind->value, self::KINDS));
        ReportFilters::whereValues($query, $filters, 'kind', 'reporting.asset_events.kind');
        ReportFilters::whereDates($query, $filters, 'occurred_on', 'reporting.asset_events.occurred_on');

        return $query->orderByDesc('reporting.asset_events.occurred_on')->orderByDesc('reporting.asset_events.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        return ReportFilters::validate($filters, [
            'kind' => ['string', Rule::in(array_map(fn (AssetEventKind $kind): string => $kind->value, self::KINDS))],
            'occurred_on' => 'date',
        ]);
    }

    /** @return array<string, list<array{id: string, label: string}>> */
    public static function choices(): array
    {
        return ['kind' => array_map(fn (AssetEventKind $kind): array => ['id' => $kind->value, 'label' => $kind->label()], self::KINDS)];
    }
}
