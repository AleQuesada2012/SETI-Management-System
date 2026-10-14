<?php

use App\Enums\SystemRole;
use App\Models\User;
use Livewire\Livewire;

/*
 * RF_92 Filtrar usuarios: Administradores and "usuarios normales" (neither Administrador nor Superusuario), by name
 * or id.
 */

beforeEach(function () {
    $this->superuser = actingAsRole(SystemRole::Superusuario);
});

test('the Superusuario filters the Administradores by name', function () {
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas']);
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Bruno Mena']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Ana Normal']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators'])
        ->set('search', 'ana')
        ->assertSee('Ana Rojas')
        ->assertDontSee('Bruno Mena')
        ->assertDontSee('Ana Normal');
});

test('the Superusuario filters the Administradores by id', function () {
    $wanted = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Carla Normal']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators'])
        ->set('search', (string) $wanted->id)
        ->assertSee('Ana Rojas')
        ->assertDontSee('Carla Normal');
});

test('usuarios normales hold neither Administrador nor Superusuario, and are filtered by name or id', function () {
    $normal = User::factory()->withRole(SystemRole::Visualizador, SystemRole::Reservante)->create(['name' => 'Daniela Quirós']);
    User::factory()->withRole(SystemRole::Reportes)->create(['name' => 'Diego Reportes']);
    User::factory()->withRole(SystemRole::Reservante, SystemRole::Administrador)->create(['name' => 'Daniel Admin']);
    User::factory()->withRole(SystemRole::Superusuario)->create(['name' => 'Dora Super']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['regular'])
        ->assertSee('Daniela Quirós')
        ->assertSee('Diego Reportes')
        ->assertDontSee('Daniel Admin')
        ->assertDontSee('Dora Super')
        ->set('search', 'quiros')
        ->assertSee('Daniela Quirós')
        ->assertDontSee('Diego Reportes')
        ->set('search', (string) $normal->id)
        ->assertSee('Daniela Quirós');
});

test('both user types together list Administradores and usuarios normales, not Superusuarios', function () {
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Carla Normal']);
    User::factory()->withRole(SystemRole::Superusuario)->create(['name' => 'Dora Super']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators', 'regular'])
        ->assertSee('Ana Rojas')
        ->assertSee('Carla Normal')
        ->assertDontSee('Dora Super');
});

test('with no Administradores registered the list says so', function () {
    User::factory()->withRole(SystemRole::Reservante)->create();

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators'])
        ->assertSee(__('users.index.no_registered.administrators'));
});

test('with no usuarios normales registered the list says so, even while searching', function () {
    User::factory()->withRole(SystemRole::Administrador)->create();

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['regular'])
        ->set('search', 'ana')
        ->assertSee(__('users.index.no_registered.regular'));
});

test('a search that matches nobody of an existing type says no users were found', function () {
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators'])
        ->set('search', 'zzz-nadie')
        ->assertSee(__('users.index.no_results'))
        ->assertDontSee(__('users.index.no_registered.administrators'));
});

test('roles without users.view cannot filter users', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('users.index', ['filters' => ['user_type' => ['values' => ['administrators']]]]))->assertForbidden();
});
