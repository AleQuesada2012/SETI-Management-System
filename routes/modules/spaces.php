<?php

use App\Http\Controllers\ShowSpacePhotoController;
use App\Models\Space;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php inside the auth + verified group. Module: spaces (RF_32–RF_45, RF_53–RF_59).

Route::prefix('espacios')->name('spaces.')->group(function () {
    Route::livewire('/', 'pages::spaces.index')
        ->middleware('can:viewAny,'.Space::class)
        ->name('index');

    Route::livewire('{space}', 'pages::spaces.show')
        ->middleware('can:view,space')
        ->name('show');

    // A space's photo (RF_41): ids in the URL, never a storage path; ?miniatura=1 for the phone thumbnail.
    Route::get('{space}/fotos/{file}', ShowSpacePhotoController::class)
        ->middleware('can:view,space')
        ->name('photos.show');
});
