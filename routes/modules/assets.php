<?php

use App\Models\Asset;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php inside the auth + verified group. Module: assets (RF_01–RF_31).

Route::prefix('activos')->name('assets.')->group(function () {
    Route::livewire('/', 'pages::assets.index')
        ->middleware('can:viewAny,'.Asset::class)
        ->name('index');

    Route::livewire('{asset}', 'pages::assets.show')
        ->middleware('can:view,asset')
        ->name('show');
});
