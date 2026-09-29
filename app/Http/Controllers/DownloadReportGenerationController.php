<?php

namespace App\Http\Controllers;

use App\Models\ReportGeneration;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The file of an exported report (RF_00_4, re-download from RF_84). The route checks `download` (ReportGenerationPolicy).
 */
class DownloadReportGenerationController extends Controller
{
    public function __invoke(ReportGeneration $generation): StreamedResponse
    {
        $file = $generation->file;

        abort_if($file === null || ! Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
