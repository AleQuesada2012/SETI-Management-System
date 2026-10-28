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

    Route::livewire('estadisticas', 'pages::reports.statistics')
        ->middleware('can:'.PermissionName::ReportsView->value)
        ->name('statistics');

    // RF_82 and RF_83 · maintenance and reservation statistics.
    Route::livewire('estadisticas/mantenimiento', 'pages::reports.maintenance-statistics')
        ->middleware('can:'.PermissionName::ReportsView->value)
        ->name('statistics.maintenance');

    Route::livewire('estadisticas/reservas', 'pages::reports.reservation-statistics')
        ->middleware('can:'.PermissionName::ReportsView->value)
        ->name('statistics.reservations');

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
