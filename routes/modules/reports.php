<?php

use App\Enums\PermissionName;
use App\Http\Controllers\DownloadReportGenerationController;
use App\Models\ReportGeneration;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php inside the auth + verified group. Module: reports (RF_00_4, RF_60–RF_84).

Route::prefix('reportes')->name('reports.')->group(function () {
    Route::livewire('/', 'pages::reports.index')
        ->middleware('can:'.PermissionName::ReportsView->value)
        ->name('index');

    // RF_84 · the history of exported reports.
    Route::livewire('generados', 'pages::reports.generations')
        ->middleware('can:viewAny,'.ReportGeneration::class)
        ->name('generations');

    // The file of one export (RF_00_4). The generation id, never a storage path, is in the URL.
    Route::get('generados/{generation}/descargar', DownloadReportGenerationController::class)
        ->middleware('can:download,generation')
        ->name('generations.download');

    // One report (RF_63, RF_64…): preview with filters, then export. {report} is a report_types.code.
    Route::livewire('{report:code}', 'pages::reports.show')
        ->middleware('can:'.PermissionName::ReportsView->value)
        ->name('show');
});
