<?php

use App\Http\Controllers\StartPageController;
use Illuminate\Support\Facades\Route;

// `/` sends guests to the sign-in page and signed-in users to their start page.
Route::get('/', fn () => to_route(auth()->check() ? 'start' : 'login'))->name('home');

/*
 * Every module page needs a signed-in, verified, active user (EnsureUserIsActive runs on every web request).
 * Each routes/modules/<module>.php adds the `can:` check of every route it declares (RF_89);
 * tests/Feature/Authorization/RouteAuthorizationTest.php fails when a module route has none.
 * URLs are Spanish (the users read them); route names are English (the code reads them).
 */
Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify's `home` (config/fortify.php): after sign-in, each user lands on the first module their roles open.
    Route::get('inicio', StartPageController::class)->name('start');

    require __DIR__.'/modules/dashboard.php';
    require __DIR__.'/modules/assets.php';
    require __DIR__.'/modules/spaces.php';
    require __DIR__.'/modules/reservations.php';
    require __DIR__.'/modules/reports.php';
    require __DIR__.'/modules/users.php';
});

require __DIR__.'/settings.php';
