<?php

namespace App\Reports;

use Carbon\CarbonInterface;

/**
 * Everything a renderer prints: the title, when and by whom it was generated, the applied filters (RF_72) and the
 * table itself.
 */
final readonly class ReportDocument
{
    /**
     * @param  array<string, string>  $filters  Spanish label => value; empty = all records
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function __construct(
        public string $title,
        public CarbonInterface $generatedAt,
        public string $generatedBy,
        public array $filters,
        public array $headers,
        public array $rows,
        public bool $landscape = false,
    ) {}
}
