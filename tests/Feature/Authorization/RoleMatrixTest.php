<?php

use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;

// RF_89 · Restricción de funcionalidades según rol: each role opens only its modules; everything else is a 403.

dataset('module pages', function () {
    $S = SystemRole::Superusuario;
    $A = SystemRole::Administrador;
    $R = SystemRole::Reportes;
    $V = SystemRole::Visualizador;
    $B = SystemRole::Reservante;

    // route name => roles that may open it (everyone else gets 403)
    return [
        'tablero' => ['dashboard', [$S, $A, $R]],
        'activos' => ['assets.index', [$S, $A, $R, $V]],
        'activo' => ['assets.show', [$S, $A, $R, $V]],
        'espacios' => ['spaces.index', [$S, $A, $R, $V, $B]],
        'espacio' => ['spaces.show', [$S, $A, $R, $V, $B]],
        'reservas' => ['reservations.index', [$S, $A, $R, $V, $B]],
        'nueva reserva' => ['reservations.create', [$S, $A, $B]],
        'reserva' => ['reservations.show', [$S, $A, $R, $V, $B]],
        'reportes' => ['reports.index', [$S, $R]],
        'reporte' => ['reports.show', [$S, $R]],
        'reportes generados' => ['reports.generations', [$S]],
        'usuarios' => ['users.index', [$S, $A]],
        'usuario' => ['users.show', [$S, $A]],
        'roles' => ['roles.index', [$S]],
    ];
});

/** The URL of a module page, creating the record it shows when it needs one. */
function modulePageUrl(string $route): string
{
    return match ($route) {
        'assets.show' => route($route, Asset::factory()->create()),
        'spaces.show' => route($route, Space::factory()->create()),
        'reservations.show' => route($route, Reservation::factory()->create()),
        'users.show' => route($route, User::factory()->create()),
        'reports.show' => route($route, 'assets_inventory'),
        default => route($route),
    };
}

test('each role opens exactly its module pages', function (string $route, array $allowed) {
    $url = modulePageUrl($route);

    foreach (SystemRole::cases() as $role) {
        $this->actingAs(userWithRole($role))
            ->get($url)
            ->assertStatus(in_array($role, $allowed, true) ? 200 : 403);
    }
})->with('module pages');

test('guests are sent to sign in from every module page', function (string $route) {
    $this->get(modulePageUrl($route))->assertRedirect(route('login'));
})->with('module pages');

test('the 403 page is in Spanish', function () {
    actingAsRole(SystemRole::Reservante);

    $this->get(route('users.index'))
        ->assertForbidden()
        ->assertSee(__('app.errors.403.title'));
});

test('the sidebar shows only the modules the user may open', function () {
    actingAsRole(SystemRole::Reservante);

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertSee('href="'.route('spaces.index').'"', false)
        ->assertSee('href="'.route('reservations.index').'"', false)
        ->assertDontSee('href="'.route('dashboard').'"', false)
        ->assertDontSee('href="'.route('assets.index').'"', false)
        ->assertDontSee('href="'.route('reports.index').'"', false)
        ->assertDontSee('href="'.route('users.index').'"', false);
});

test('a user with several roles gets the union of their permissions', function () {
    actingAsRole(SystemRole::Visualizador, SystemRole::Reservante);

    $this->get(route('assets.index'))->assertOk();
    $this->get(route('reservations.create'))->assertOk();
    $this->get(route('users.index'))->assertForbidden();
});

test('after sign-in each role lands on the first module it may open', function (SystemRole $role, string $expected) {
    actingAsRole($role);

    $this->get(route('start'))->assertRedirect(route($expected));
})->with([
    [SystemRole::Superusuario, 'dashboard'],
    [SystemRole::Administrador, 'dashboard'],
    [SystemRole::Reportes, 'dashboard'],
    [SystemRole::Visualizador, 'assets.index'],
    [SystemRole::Reservante, 'spaces.index'],
]);

test('signed-in users are sent from / to their start page', function () {
    actingAsRole(SystemRole::Reservante);

    $this->get('/')->assertRedirect(route('start'));
});
