<?php

use App\Enums\SystemRole;
use App\Models\User;
use Livewire\Livewire;

/*
 * RF_101 Consultar usuarios por estado de cuenta.
 *
 * Every user gets a fixed email: the search also matches emails, and a random Faker email that happened to contain
 * the searched text made a "should not match" assertion flaky in CI.
 */

beforeEach(function () {
    actingAsRole(SystemRole::Superusuario);
});

test('choosing an account status lists only the users with it', function () {
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Ana Activa', 'email' => 'ana.activa@seti.test']);
    User::factory()->deactivated()->withRole(SystemRole::Reservante)->create(['name' => 'Beto Desactivado', 'email' => 'beto.desactivado@seti.test']);

    Livewire::test('pages::users.index')
        ->set('filters.account_status.values', ['deactivated'])
        ->assertSee('Beto Desactivado')
        ->assertDontSee('Ana Activa')
        ->set('filters.account_status.values', ['active'])
        ->assertSee('Ana Activa')
        ->assertDontSee('Beto Desactivado');
});

test('with no deactivated accounts the list says so', function () {
    User::factory()->withRole(SystemRole::Reservante)->create();

    Livewire::test('pages::users.index')
        ->set('filters.account_status.values', ['deactivated'])
        ->assertSee(__('users.index.no_status.deactivated'))
        ->assertDontSee(__('users.index.no_results'));
});

test('the status message wins even while searching', function () {
    Livewire::test('pages::users.index')
        ->set('filters.account_status.values', ['deactivated'])
        ->set('search', 'ana')
        ->assertSee(__('users.index.no_status.deactivated'));
});

test('a status that exists but matches nothing with the other criteria says no users were found', function () {
    User::factory()->deactivated()->withRole(SystemRole::Reservante)->create(['name' => 'Beto Desactivado', 'email' => 'beto.desactivado@seti.test']);

    Livewire::test('pages::users.index')
        ->set('filters.account_status.values', ['deactivated'])
        ->set('search', 'zzz-nadie')
        ->assertSee(__('users.index.no_results'))
        ->assertDontSee(__('users.index.no_status.deactivated'));
});
