<?php

namespace App\Models;

use App\Casts\JsonObject;
use App\Enums\ReportFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One export of a report, kept with its filters, format, author, time and file (CONTEXT.md
 * "Report generation", RF_00_4, RF_72, RF_84). Written only by App\Actions\Reports\ExportReport.
 *
 * @property int $id
 * @property int $report_type_id
 * @property ReportFormat $format
 * @property array<string, mixed> $filters {} means "all records" (RF_72)
 * @property int $row_count
 * @property int|null $file_id
 * @property int $generated_by
 * @property Carbon $generated_at
 */
class ReportGeneration extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ReportFormat::class,
            'filters' => JsonObject::class,
            'row_count' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReportType, $this>
     */
    public function reportType(): BelongsTo
    {
        return $this->belongsTo(ReportType::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function auditLabel(): string
    {
        $this->loadMissing('reportType');

        return "{$this->reportType?->name} · {$this->format->label()}";
    }
}
