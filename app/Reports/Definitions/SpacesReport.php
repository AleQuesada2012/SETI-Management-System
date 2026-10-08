<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\SpaceRow;
use App\Queries\Spaces\SpaceDirectory;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** RF_64: live space information, including composed location and all assigned managers. */
final class SpacesReport extends ReportDefinition
{
    public function code(): string
    {
        return 'spaces';
    }

    public function columns(): array
    {
        $columns = [];
        foreach (['name', 'location', 'capacity', 'space_type', 'status', 'managers'] as $key) {
            $columns[$key] = __('reports.columns.'.($key === 'name' ? 'space_report_name' : 'space_'.$key));
        }

        return $columns;
    }

    /** @return Builder<SpaceRow> */
    public function query(array $filters): Builder
    {
        return SpaceDirectory::query(SpaceDirectory::validate($filters));
    }

    public function filterKeys(): array
    {
        return ['search', ...array_keys(SpaceDirectory::CRITERIA)];
    }

    public function normalizeFilters(array $filters): array
    {
        return SpaceDirectory::validate($filters);
    }

    public function filterFields(): array
    {
        $fields = ['search' => ['label' => __('reports.filters.labels.search'), 'type' => 'text']];
        foreach (SpaceDirectory::choices() as $key => $choices) {
            $fields[$key] = ['label' => __('reports.filters.labels.'.$key), 'type' => 'values', 'options' => $choices];
        }

        return $fields;
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        return $key === 'location' && $row instanceof SpaceRow ? $row->location() : parent::cell($row, $key);
    }
}
