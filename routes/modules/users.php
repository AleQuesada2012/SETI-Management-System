<?php

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php inside the auth + verified group. Module: users and roles (RF_85–RF_101).

Route::prefix('usuarios')->name('users.')->group(function () {
    Route::livewire('/', 'pages::users.index')
        ->middleware('can:viewAny,'.User::class)
        ->name('index');

    // RF_96: before {user}, so "encargados" is never read as a user id. Superusuario only (spaces.assign_managers).
    Route::livewire('encargados', 'pages::users.space-managers')
        ->middleware('can:'.PermissionName::SpacesAssignManagers->value)
        ->name('space-managers');

    Route::livewire('{user}', 'pages::users.show')
        ->middleware('can:view,user')
        ->name('show');
});

Route::prefix('roles')->name('roles.')->group(function () {
    Route::livewire('/', 'pages::roles.index')
        ->middleware('can:viewAny,'.Role::class)
        ->name('index');
});
