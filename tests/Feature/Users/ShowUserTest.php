<?php

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * RF_94 Consultar información de usuario.
 */

test('an Administrador opens a user from the list and sees name, email, roles and account status', function () {
    actingAsRole(SystemRole::Administrador);
    $user = User::factory()->withRole(SystemRole::Reservante, SystemRole::Visualizador)
        ->create(['name' => 'Laura Vargas', 'email' => 'lvargas@tec.ac.cr']);

    $this->get(route('users.index'))->assertSee(route('users.show', $user), escape: false);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('Laura Vargas')
        ->assertSee('lvargas@tec.ac.cr')
        ->assertSee('Reservante')
        ->assertSee('Visualizador')
        ->assertSee('Activa');
});

test('the detail shows a deactivated account and a pending invitation', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->invited()->create(['deactivated_at' => Carbon::parse('2026-09-15 14:30')]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('Desactivada')
        ->assertSee('Invitación pendiente')
        ->assertSee('15/09/2026 14:30');
});

test('a user that does not exist is reported as not found', function () {
    actingAsRole(SystemRole::Superusuario);

    $this->get(route('users.show', 999_999))
        ->assertNotFound()
        ->assertSee(__('app.errors.404.title'));
});

test('roles without users.view cannot open a user detail', function (SystemRole $role) {
    actingAsRole($role);
    $user = User::factory()->create();

    $this->get(route('users.show', $user))->assertForbidden();
})->with([SystemRole::Visualizador, SystemRole::Reservante, SystemRole::Reportes]);
