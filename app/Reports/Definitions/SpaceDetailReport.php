<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\AssetRow;
use App\Models\Reporting\SpaceRow;
use App\Queries\Assets\AssetInventory;
use App\Queries\Spaces\SpaceDirectory;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * RF_74 · The report of one space (the space page, RF_35, exported): the space's name, location, capacity, type,
 * status and managers, then the assets currently in it with their identification, name, brand, model, condition and
 * status. Copies RF_63: the rows are AssetInventory's, filtered by the inventory contract's `space` criterion; the
 * space's details are printed in the report's information block (describeFilters), which both renderers show.
 * Implemented by @Dizabu (planning round 3); reports owner @AleQuesada2012 reviews.
 *
 *     <livewire:reports.export-button report="space_detail" :filters="['space' => ['values' => [$space->id]]]" />
 */
final class SpaceDetailReport extends ReportDefinition
{
    public function code(): string
    {
        return 'space_detail';
    }

    public function columns(): array
    {
        $columns = [];
        foreach (['code', 'asset_tag', 'display_name', 'brand', 'model', 'condition', 'status'] as $key) {
            $columns[$key] = __('reports.columns.'.$key);
        }

        return $columns;
    }

    /** @return Builder<AssetRow> */
    public function query(array $filters): Builder
    {
        return AssetInventory::query(['space' => ['values' => [$this->spaceId($filters)]]])->reorder()->orderBy('code');
    }

    public function filterKeys(): array
    {
        return ['space'];
    }

    /**
     * Exactly one existing space is required: "all records" means nothing for a single-space report.
     *
     * @param  array<string, mixed>  $filters
     * @return array{space: array{values: list<int>}}
     */
    public function normalizeFilters(array $filters): array
    {
        $filters = parent::normalizeFilters($filters);
        $values = array_values(array_unique(array_map('intval', (array) ($filters['space']['values'] ?? []))));

        if (count($values) !== 1 || ! SpaceRow::query()->whereKey($values[0])->exists()) {
            throw ValidationException::withMessages(['space' => __('reports.space_detail.choose_one')]);
        }

        return ['space' => ['values' => $values]];
    }

    /** The space's details, printed in the report's information block (RF_74's first criterion). */
    public function describeFilters(array $filters): array
    {
        $space = SpaceDirectory::query()->whereKey($this->spaceId($filters))->firstOrFail();

        return [
            __('reports.space_detail.space') => "{$space->code} · {$space->name}",
            __('reports.space_detail.location') => $space->location(),
            __('reports.space_detail.capacity') => trans_choice('reports.space_detail.people', $space->capacity, ['count' => $space->capacity]),
            __('reports.space_detail.type') => $space->space_type,
            __('reports.space_detail.status') => $space->status,
            __('reports.space_detail.managers') => $space->managers ?? __('reports.space_detail.no_managers'),
        ];
    }

    public function filterFields(): array
    {
        return ['space' => [
            'label' => __('reports.filters.labels.space'),
            'type' => 'values',
            'options' => SpaceDirectory::query()->reorder()->orderBy('code')->get()
                ->map(fn (SpaceRow $space): array => ['id' => $space->id, 'label' => "{$space->code} · {$space->name}", 'removed' => false])
                ->all(),
        ]];
    }

    /** @param array<string, mixed> $filters */
    private function spaceId(array $filters): int
    {
        return (int) ($filters['space']['values'][0] ?? 0);
    }
}
