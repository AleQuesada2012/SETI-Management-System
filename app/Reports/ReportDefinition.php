<?php

namespace App\Reports;

use App\Enums\PermissionName;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * One report of the catalog (report_types, RF_63–RF_79): what it reads, which columns it shows and which filters it
 * accepts. The preview page and the export (RF_00_4) both use it, so the file always matches what was previewed.
 *
 * To add a report: extend this class in app/Reports/Definitions, read a reporting.* view through the module's Query
 * (e.g. AssetInventory::query($filters)) so lists and reports agree, and register the class in ReportRegistry.
 */
abstract class ReportDefinition
{
    /** The report_types.code this definition renders (database/seeders/ReportTypeSeeder.php). */
    abstract public function code(): string;

    /**
     * Column key (an attribute of the rows) => Spanish header, in display order.
     *
     * @return array<string, string>
     */
    abstract public function columns(): array;

    /**
     * The rows, already filtered and sorted.
     *
     * @param  array<string, mixed>  $filters  normalized (see normalizeFilters)
     * @return Builder<covariant Model>
     */
    abstract public function query(array $filters): Builder;

    /**
     * Filter keys this report accepts; anything else is dropped before querying and before storing the generation.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return [];
    }

    /**
     * Spanish label => readable value of every applied filter, printed on the report (RF_72). An empty array means
     * "all records". Override it to turn ids into names.
     *
     * @param  array<string, mixed>  $filters  normalized
     * @return array<string, string>
     */
    public function describeFilters(array $filters): array
    {
        return array_map(fn (mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value, $filters);
    }

    /** Permission needed to preview and export it, on top of reports.view / reports.export (e.g. reports.audit). */
    public function permission(): PermissionName
    {
        return PermissionName::ReportsView;
    }

    /** Wide reports print in landscape. */
    public function landscape(): bool
    {
        return count($this->columns()) > 6;
    }

    /**
     * Keep only known filter keys with a value: this is what gets queried and stored ({} = all records, RF_72).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        return array_filter(
            Arr::only($filters, $this->filterKeys()),
            fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
        );
    }

    /**
     * The printable cells of one row, in columns() order.
     *
     * @return list<string|int|float|null>
     */
    public function cells(Model $row): array
    {
        return array_map(fn (string $key) => $this->cell($row, $key), array_keys($this->columns()));
    }

    /** Override to format a specific column; the default handles enums, dates and booleans. */
    protected function cell(Model $row, string $key): string|int|float|null
    {
        $value = $row->getAttribute($key);

        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => method_exists($value, 'label') ? $value->label() : (string) $value->value,
            $value instanceof CarbonInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i'),
            is_bool($value) => $value ? 'Sí' : 'No',
            is_int($value), is_float($value) => $value,
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
