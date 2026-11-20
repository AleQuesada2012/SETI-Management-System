<?php

use App\Http\Controllers\ShowAssetFileController;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php inside the auth + verified group. Module: assets (RF_01–RF_31).

Route::prefix('activos')->name('assets.')->group(function () {
    Route::livewire('/', 'pages::assets.index')
        ->middleware('can:viewAny,'.Asset::class)
        ->name('index');

    // Before {asset}, so "nuevo" is not read as an asset id.
    Route::livewire('nuevo', 'pages::assets.create')
        ->middleware('can:create,'.Asset::class)
        ->name('create');

    // withTrashed: Livewire runs this route's middleware again on every update of the page's components, so a click
    // after another tab deleted the asset must still bind it; assets.delete-asset then reports it as gone (CP-02 c).
    // The page itself answers 404 for a deleted asset, and every Action reloads the asset without the deleted ones.
    Route::livewire('{asset}', 'pages::assets.show')
        ->middleware('can:view,asset')
        ->withTrashed()
        ->name('show');

    Route::livewire('{asset}/editar', 'pages::assets.edit')
        ->middleware('can:update,asset')
        ->name('edit');

    // RF_09: where the asset has been, what happened to it and who changed it.
    Route::livewire('{asset}/historial', 'pages::assets.history')
        ->middleware('can:view,asset')
        ->name('history');

    Route::livewire('{asset}/movimiento', 'pages::assets.move')
        ->middleware('can:move,asset')
        ->name('move');

    // An asset's document or photo (RF_20, RF_21): ids in the URL, never a storage path; ?miniatura=1 for a photo's
    // phone thumbnail.
    Route::get('{asset}/archivos/{file}', ShowAssetFileController::class)
        ->middleware('can:view,asset')
        ->name('files.show');
});

// RF_27: the scheduled maintenance of every asset; each one is managed on its asset's "Mantenimientos" card.
Route::livewire('mantenimientos', 'pages::assets.maintenance')
    ->middleware('can:viewAny,'.MaintenanceRecord::class)
    ->name('maintenance.index');
