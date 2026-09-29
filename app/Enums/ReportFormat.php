<?php

namespace App\Enums;

/**
 * Export format of a report (report_generations.format, RF_00_4, RF_70, RF_71).
 */
enum ReportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Xlsx => 'Excel (XLSX)',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
