<?php

use App\Models\Asset;
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

    Route::livewire('{asset}', 'pages::assets.show')
        ->middleware('can:view,asset')
        ->name('show');

    Route::livewire('{asset}/editar', 'pages::assets.edit')
        ->middleware('can:update,asset')
        ->name('edit');
});
