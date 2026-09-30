<?php

namespace App\Reports;

use App\Enums\PermissionName;
use App\Enums\ReportCategory;
use App\Models\ReportType;
use InvalidArgumentException;

/**
 * report_types.code => the ReportDefinition that renders it. A report type without a definition shows as
 * "not available yet" in the reports menu. Bound as a singleton (AppServiceProvider).
 */
final class ReportRegistry
{
    /** @var array<string, class-string<ReportDefinition>> */
    private array $definitions = [
        'assets_inventory' => Definitions\AssetInventoryReport::class,
    ];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $requirements = null;

    /** Authorization remains available before a report's implementation exists. */
    public function permissionFor(ReportType $type): PermissionName
    {
        return $this->has($type->code) ? $this->get($type->code)->permission()
            : ($type->category === ReportCategory::Audit ? PermissionName::ReportsAudit : PermissionName::ReportsView);
    }

    /** Only approved future-sprint requirements appear as upcoming; unknown types remain hidden. */
    public function isUpcoming(ReportType $type): bool
    {
        $this->requirements ??= array_column(json_decode((string) file_get_contents(base_path('docs/requirements/requirements.json')), true, flags: JSON_THROW_ON_ERROR), null, 'code');
        $requirement = $this->requirements[$type->description ?? ''] ?? null;

        return $requirement !== null && $requirement['sprint'] > config('reports.current_sprint');
    }

    /** @param  class-string<ReportDefinition>  $definition */
    public function register(string $definition): void
    {
        $this->definitions[app($definition)->code()] = $definition;
    }

    public function has(string $code): bool
    {
        return isset($this->definitions[$code]);
    }

    public function get(string $code): ReportDefinition
    {
        if (! $this->has($code)) {
            throw new InvalidArgumentException("No report definition is registered for report type [{$code}].");
        }

        return app($this->definitions[$code]);
    }
}
