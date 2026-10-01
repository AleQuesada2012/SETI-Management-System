<?php

use App\Enums\SystemRole;
use App\Models\User;
use Livewire\Livewire;

/*
 * RF_95 Buscar usuarios.
 */

test('the search matches part of the name, ignoring accents and case', function () {
    actingAsRole(SystemRole::Superusuario);
    User::factory()->create(['name' => 'Andrés Jiménez']);
    User::factory()->create(['name' => 'Carolina Mora']);

    Livewire::test('pages::users.index')
        ->set('search', 'jimenez')
        ->assertSee('Andrés Jiménez')
        ->assertDontSee('Carolina Mora');
});

test('the search matches a user by id', function () {
    actingAsRole(SystemRole::Superusuario);
    $wanted = User::factory()->create(['name' => 'Andrés Jiménez']);

    Livewire::test('pages::users.index')
        ->set('search', (string) $wanted->id)
        ->assertSee('Andrés Jiménez');
});

test('a search without matches says no users were found', function () {
    actingAsRole(SystemRole::Superusuario);

    Livewire::test('pages::users.index')
        ->set('search', 'zzz-nadie')
        ->assertSee(__('users.index.no_results'));
});

test('roles without users.view cannot search users', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('users.index', ['search' => 'jimenez']))->assertForbidden();
});
