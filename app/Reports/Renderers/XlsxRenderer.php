<?php

namespace App\Reports\Renderers;

use App\Reports\ReportDocument;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * RF_71 · XLSX export with spatie/simple-excel. Sheet "Datos" holds the header row and the records, ready to filter
 * and analyze; sheet "Información" holds the title, generation date, author and filters (RF_72).
 */
final class XlsxRenderer implements ReportRenderer
{
    public function render(ReportDocument $document): string
    {
        // simple-excel picks the format from the extension, so the temporary file needs one.
        $base = (string) tempnam(sys_get_temp_dir(), 'seti-report-');
        $path = $base.'.xlsx';

        try {
            $writer = SimpleExcelWriter::create($path)->noHeaderRow();
            $writer->nameCurrentSheet(__('reports.export.sheet_data'));
            $writer->addRow($document->headers);

            foreach ($document->rows as $row) {
                $writer->addRow($row);
            }

            $writer->addNewSheetAndMakeItCurrent(__('reports.export.sheet_info'));
            $writer->addRow([__('reports.document.report'), $document->title]);
            $writer->addRow([__('reports.document.generated_at'), $document->generatedAt->format('d/m/Y H:i')]);
            $writer->addRow([__('reports.document.generated_by'), $document->generatedBy]);
            $writer->addRow([__('reports.document.records'), count($document->rows)]);

            if ($document->filters === []) {
                $writer->addRow([__('reports.document.filters'), __('reports.document.all_records')]);
            }

            foreach ($document->filters as $label => $value) {
                $writer->addRow([__('reports.document.filter', ['filter' => $label]), $value]);
            }

            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
            @unlink($base);
        }
    }
}
