<?php

use Illuminate\Support\Facades\Route;

// The signed-in user's own settings: profile, password and 2FA, appearance.

Route::middleware(['auth'])->group(function () {
    Route::redirect('configuracion', 'configuracion/perfil');
    Route::livewire('configuracion/perfil', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('configuracion/apariencia', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('configuracion/seguridad', 'pages::settings.security')
        ->middleware(['password.confirm'])
        ->name('security.edit');
});
