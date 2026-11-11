<?php

use App\Enums\SystemRole;
use App\Models\User;
use Livewire\Livewire;

/*
 * RF_92 Filtrar usuarios: Administradores and "usuarios normales" (neither Administrador nor Superusuario), by name
 * or id.
 *
 * Every user gets a fixed email: the search also matches emails, and a random Faker email that happened to contain
 * the searched text made a "should not match" assertion flaky in CI.
 */

beforeEach(function () {
    $this->superuser = actingAsRole(SystemRole::Superusuario);
});

test('the Superusuario filters the Administradores by name', function () {
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas', 'email' => 'ana.rojas@seti.test']);
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Bruno Mena', 'email' => 'bruno.mena@seti.test']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Ana Normal', 'email' => 'ana.normal@seti.test']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators'])
        ->set('search', 'ana')
        ->assertSee('Ana Rojas')
        ->assertDontSee('Bruno Mena')
        ->assertDontSee('Ana Normal');
});

test('the Superusuario filters the Administradores by id', function () {
    $wanted = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas', 'email' => 'ana.rojas@seti.test']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Carla Normal', 'email' => 'carla.normal@seti.test']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators'])
        ->set('search', (string) $wanted->id)
        ->assertSee('Ana Rojas')
        ->assertDontSee('Carla Normal');
});

test('usuarios normales hold neither Administrador nor Superusuario, and are filtered by name or id', function () {
    $normal = User::factory()->withRole(SystemRole::Visualizador, SystemRole::Reservante)->create(['name' => 'Daniela Quirós', 'email' => 'daniela.quiros@seti.test']);
    User::factory()->withRole(SystemRole::Reportes)->create(['name' => 'Diego Reportes', 'email' => 'diego.reportes@seti.test']);
    User::factory()->withRole(SystemRole::Reservante, SystemRole::Administrador)->create(['name' => 'Daniel Admin', 'email' => 'daniel.admin@seti.test']);
    User::factory()->withRole(SystemRole::Superusuario)->create(['name' => 'Dora Super', 'email' => 'dora.super@seti.test']);

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
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas', 'email' => 'ana.rojas@seti.test']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Carla Normal', 'email' => 'carla.normal@seti.test']);
    User::factory()->withRole(SystemRole::Superusuario)->create(['name' => 'Dora Super', 'email' => 'dora.super@seti.test']);

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

test('every selected user type excludes a Superusuario with additional roles', function (array $types) {
    User::factory()->withRole(SystemRole::Administrador, SystemRole::Superusuario)->create(['name' => 'Eva Multirrol', 'email' => 'eva.multirrol@seti.test']);
    User::factory()->withRole(SystemRole::Reservante, SystemRole::Superusuario)->create(['name' => 'Fabián Multirrol', 'email' => 'fabian.multirrol@seti.test']);
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Gina Administradora', 'email' => 'gina.administradora@seti.test']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Hugo Normal', 'email' => 'hugo.normal@seti.test']);

    Livewire::test('pages::users.index')
        ->assertSee('Eva Multirrol')
        ->assertSee('Fabián Multirrol')
        ->set('filters.user_type.values', $types)
        ->assertDontSee('Eva Multirrol')
        ->assertDontSee('Fabián Multirrol')
        ->call('clearFilters')
        ->assertSee('Eva Multirrol')
        ->assertSee('Fabián Multirrol');
})->with([
    'administrators' => [['administrators']],
    'regular' => [['regular']],
    'both types' => [['administrators', 'regular']],
]);

test('with no usuarios normales registered the list says so, even while searching', function () {
    User::factory()->withRole(SystemRole::Administrador)->create();

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['regular'])
        ->set('search', 'ana')
        ->assertSee(__('users.index.no_registered.regular'));
});

test('a search that matches nobody of an existing type says no users were found', function () {
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Rojas', 'email' => 'ana.rojas@seti.test']);

    Livewire::test('pages::users.index')
        ->set('filters.user_type.values', ['administrators'])
        ->set('search', 'zzz-nadie')
        ->assertSee(__('users.index.no_results'))
        ->assertDontSee(__('users.index.no_registered.administrators'));
});
