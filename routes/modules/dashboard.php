<?php

use App\Enums\PermissionName;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php inside the auth + verified group. Owner: platform (dashboard KPIs over reporting.dashboard_kpis).

Route::livewire('tablero', 'pages::dashboard')
    ->middleware('can:'.PermissionName::DashboardsView->value)
    ->name('dashboard');
