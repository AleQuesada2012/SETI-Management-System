<?php

use App\Http\Controllers\PowerBiFeedController;
use App\Http\Middleware\AuthenticatePowerBiFeed;
use App\Reports\PowerBiFeed;
use Illuminate\Support\Facades\Route;

/*
 * The Power BI feed (RF_61, RF_73), loaded by bootstrap/app.php outside the `web` group: Power BI Service calls it
 * with the HTTP Basic credential of `.env`, never with a user session, so it gets no session, cookies or CSRF.
 * Throttled before authentication so the credential cannot be guessed quickly.
 */
Route::get('powerbi/{dataset}.csv', PowerBiFeedController::class)
    ->middleware(['throttle:60,1', AuthenticatePowerBiFeed::class])
    ->whereIn('dataset', array_keys(PowerBiFeed::DATASETS))
    ->name('powerbi.feed');
