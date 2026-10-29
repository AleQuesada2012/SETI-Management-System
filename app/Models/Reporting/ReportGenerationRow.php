<?php

namespace App\Models\Reporting;

use App\Enums\ReportCategory;
use App\Enums\ReportFormat;
use Illuminate\Support\Carbon;

/**
 * One immutable generation record from reporting.report_generations (RF_84).
 *
 * @property int $id
 * @property string $report_name
 * @property ReportCategory $report_category
 * @property ReportFormat $format
 * @property string $generated_by_name
 * @property Carbon $generated_at
 * @property int $row_count
 * @property bool $file_available
 */
class ReportGenerationRow extends ReportingView
{
    protected $table = 'reporting.report_generations';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'format' => ReportFormat::class,
            'report_category' => ReportCategory::class, 'row_count' => 'integer', 'file_available' => 'boolean'];
    }
}
