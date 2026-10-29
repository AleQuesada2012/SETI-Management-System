<?php

namespace App\Queries\Reports;

use App\Models\Reporting\AssetComponentRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/** RF_77: the registered components of current assets, grouped by asset. */
final class ComponentList
{
    /**
     * @param  array<string, mixed>  $filters  normalized by validate()
     * @return Builder<AssetComponentRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = ReportFilters::withCurrentAsset(AssetComponentRow::query());
        ReportFilters::whereValues($query, $filters, 'asset', 'reporting.asset_components.asset_id');

        return $query->orderBy('reporting.asset_components.asset_code')->orderBy('reporting.asset_components.asset_id')
            ->orderBy('reporting.asset_components.name')->orderBy('reporting.asset_components.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        return ReportFilters::validate($filters, ['asset' => ['integer', Rule::exists('assets', 'id')]]);
    }

    /** @return array<string, list<array{id: int, label: string}>> */
    public static function choices(): array
    {
        return ['asset' => ReportFilters::assetChoices(AssetComponentRow::query())];
    }
}
