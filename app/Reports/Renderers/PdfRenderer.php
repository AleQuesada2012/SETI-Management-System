<?php

namespace App\Reports\Renderers;

use App\Reports\ReportDocument;
use Barryvdh\DomPDF\Facade\Pdf;

/** RF_70 · PDF export with dompdf; the layout is resources/views/reports/pdf.blade.php. */
final class PdfRenderer implements ReportRenderer
{
    public function render(ReportDocument $document): string
    {
        return Pdf::loadView('reports.pdf', ['document' => $document])
            ->setPaper('letter', $document->landscape ? 'landscape' : 'portrait')
            ->output();
    }
}
