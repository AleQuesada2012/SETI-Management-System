<?php

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

/*
 * RF_90 Listar usuarios.
 */

test('an Administrador sees every user with name, email, roles and account status', function () {
    actingAsRole(SystemRole::Administrador);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Laura Vargas', 'email' => 'lvargas@tec.ac.cr']);
    User::factory()->deactivated()->withRole(SystemRole::Visualizador)->create(['name' => 'Mario Solís']);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Laura Vargas')
        ->assertSee('lvargas@tec.ac.cr')
        ->assertSee('Reservante')
        ->assertSee('Mario Solís')
        ->assertSee('Desactivada');
});

test('filters without matches say no users were found', function () {
    actingAsRole(SystemRole::Superusuario);
    // A deactivated account exists, so the empty list comes from combining the criteria (RF_101 covers "none at all").
    User::factory()->deactivated()->withRole(SystemRole::Visualizador)->create();

    Livewire::test('pages::users.index')
        ->set('filters.role.values', [(string) Role::findByName(SystemRole::Reportes->value)->id])
        ->set('filters.account_status.values', ['deactivated'])
        ->assertSee(__('users.index.no_results'));
});

test('the list filters by role and by account status', function () {
    actingAsRole(SystemRole::Superusuario);
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Admin Activo']);
    User::factory()->deactivated()->withRole(SystemRole::Administrador)->create(['name' => 'Admin Inactivo']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Reservante Activo']);

    Livewire::test('pages::users.index')
        ->set('filters.role.values', [(string) Role::findByName(SystemRole::Administrador->value)->id])
        ->assertSee('Admin Activo')
        ->assertSee('Admin Inactivo')
        ->assertDontSee('Reservante Activo')
        ->set('filters.account_status.values', ['deactivated'])
        ->assertSee('Admin Inactivo')
        ->assertDontSee('Admin Activo');
});
