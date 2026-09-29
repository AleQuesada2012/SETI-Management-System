<?php

namespace App\Reports\Renderers;

use App\Reports\ReportDocument;

interface ReportRenderer
{
    /** The bytes of the exported file. */
    public function render(ReportDocument $document): string;
}
