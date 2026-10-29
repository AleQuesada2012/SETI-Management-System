<?php

namespace App\Queries\Reports;

use App\Models\Reporting\ReportGenerationRow;
use Illuminate\Database\Eloquent\Builder;

/** RF_84: preserve all generations, including those whose file was removed. */
final class ReportHistory
{
    /** @return Builder<ReportGenerationRow> */
    public static function query(): Builder
    {
        return ReportGenerationRow::query()->orderByDesc('generated_at')->orderByDesc('id');
    }
}
