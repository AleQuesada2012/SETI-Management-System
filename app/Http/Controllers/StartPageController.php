<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Asset;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/inicio`, Fortify's home: sends each user to the first module their roles open, in sidebar order.
 * A Reservante lands on Espacios, a Visualizador on Activos, Reportes and Administrador on the Tablero.
 */
class StartPageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $route = match (true) {
            $user->can(PermissionName::DashboardsView) => 'dashboard',
            $user->can('viewAny', Asset::class) => 'assets.index',
            $user->can('viewAny', Space::class) => 'spaces.index',
            $user->can('viewAny', Reservation::class) => 'reservations.index',
            $user->can(PermissionName::ReportsView) => 'reports.index',
            $user->can('viewAny', User::class) => 'users.index',
            $user->can('viewAny', Role::class) => 'roles.index',
            default => 'profile.edit',
        };

        return to_route($route);
    }
}
