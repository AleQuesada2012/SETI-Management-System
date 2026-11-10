<?php

use App\Enums\SystemRole;
use App\Models\User;
use Livewire\Livewire;

/*
 * RF_95 Buscar usuarios.
 *
 * Every user gets a fixed email: the search also matches emails, and a random Faker email that happened to contain
 * the searched text made a "should not match" assertion flaky in CI.
 */

test('the search matches part of the name, ignoring accents and case', function () {
    actingAsRole(SystemRole::Superusuario);
    User::factory()->create(['name' => 'Andrés Jiménez', 'email' => 'andres.jimenez@seti.test']);
    User::factory()->create(['name' => 'Carolina Mora', 'email' => 'carolina.mora@seti.test']);

    Livewire::test('pages::users.index')
        ->set('search', 'jimenez')
        ->assertSee('Andrés Jiménez')
        ->assertDontSee('Carolina Mora');
});

test('the search matches a user by id', function () {
    actingAsRole(SystemRole::Superusuario);
    $wanted = User::factory()->create(['name' => 'Andrés Jiménez', 'email' => 'andres.jimenez@seti.test']);

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
