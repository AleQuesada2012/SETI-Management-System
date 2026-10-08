<?php

use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

/*
 * RF_100 Consultar roles disponibles.
 */

test('the Superusuario sees every role with its description and what it allows', function () {
    actingAsRole(SystemRole::Superusuario);

    $response = $this->get(route('roles.index'))->assertOk();

    foreach (SystemRole::cases() as $role) {
        $response->assertSee($role->value)->assertSee($role->description());
    }

    $response->assertSee(PermissionName::ReservationsCreate->description())
        ->assertSee(PermissionName::UsersDeactivate->description());
});

test('selecting a role lists the users who hold it', function () {
    actingAsRole(SystemRole::Superusuario);
    User::factory()->withRole(SystemRole::Reportes)->create(['name' => 'Andrea Reportes']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Bruno Reservante']);

    $reportes = Role::findByName(SystemRole::Reportes->value);

    Livewire::test('pages::roles.index')
        ->assertDontSee('Andrea Reportes')
        ->assertSeeHtml('wire:click="select('.$reportes->id.')"')
        ->call('select', $reportes->id)
        ->assertSee(__('users.roles.holders_heading', ['role' => 'Reportes']))
        ->assertSee('Andrea Reportes')
        ->assertDontSee('Bruno Reservante')
        ->call('select', $reportes->id)
        ->assertDontSee('Andrea Reportes');
});

test('a role nobody holds says so', function () {
    actingAsRole(SystemRole::Superusuario);

    Livewire::test('pages::roles.index')
        ->call('select', Role::findByName(SystemRole::Visualizador->value)->id)
        ->assertSee(__('users.roles.no_holders'));
});

test('roles without roles.view cannot open the roles page', function (SystemRole $role) {
    actingAsRole($role);

    $this->get(route('roles.index'))->assertForbidden();
})->with([SystemRole::Administrador, SystemRole::Reportes, SystemRole::Visualizador, SystemRole::Reservante]);

test('selecting a role is forbidden when the current user no longer has roles.view', function () {
    actingAsRole(SystemRole::Superusuario);
    $component = Livewire::test('pages::roles.index');
    $reportes = Role::findByName(SystemRole::Reportes->value);

    actingAsRole(SystemRole::Administrador);

    $component->call('select', $reportes->id)->assertForbidden();
});
