<?php

namespace App\Reports;

use InvalidArgumentException;

/**
 * report_types.code => the ReportDefinition that renders it. A report type without a definition shows as
 * "not available yet" in the reports menu. Bound as a singleton (AppServiceProvider).
 */
final class ReportRegistry
{
    /** @var array<string, class-string<ReportDefinition>> */
    private array $definitions = [
        // 'assets_inventory' => Definitions\AssetInventoryReport::class,  // RF_63
        // 'spaces' => Definitions\SpacesReport::class,                    // RF_64
    ];

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
