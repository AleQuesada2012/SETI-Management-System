<?php

use App\Models\Reservation;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php inside the auth + verified group. Module: reservations (RF_45–RF_52).

Route::prefix('reservas')->name('reservations.')->group(function () {
    // RF_59. Before {reservation}, so "ocupacion" is not read as a reservation id.
    Route::livewire('ocupacion', 'pages::reservations.occupancy')
        ->middleware('can:viewAny,'.Reservation::class)
        ->name('occupancy');

    Route::livewire('/', 'pages::reservations.index')
        ->middleware('can:viewAny,'.Reservation::class)
        ->name('index');

    // Declared before {reservation} so "nueva" and "historial" are not read as reservation ids.
    Route::livewire('nueva', 'pages::reservations.create')
        ->middleware('can:create,'.Reservation::class)
        ->name('create');

    Route::livewire('historial', 'pages::reservations.history')
        ->middleware('can:viewAny,'.Reservation::class)
        ->name('history');

    Route::livewire('{reservation}', 'pages::reservations.show')
        ->middleware('can:view,reservation')
        ->name('show');

    Route::livewire('{reservation}/editar', 'pages::reservations.edit')
        ->middleware('can:update,reservation')
        ->name('edit');
});
