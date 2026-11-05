<?php

use App\Enums\SystemRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * RF_97 Consultar laboratorios asignados a un administrador (any space type, ADR-0009).
 */

/** The HTML of the "Por administrador" section only: the "Por espacio" select above lists every space. */
function byAdministratorSection(string $html): string
{
    return Str::after($html, 'data-test="by-administrator"');
}

beforeEach(function () {
    actingAsRole(SystemRole::Superusuario);
});

test('the Superusuario picks an Administrador and sees the spaces in their charge', function () {
    $lucia = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Lucía Campos']);
    $mario = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Mario Solís']);

    Carbon::setTestNow('2026-10-15 20:15');
    // A name the factory never generates (it makes "Laboratorio <random number>"), so it cannot collide.
    Space::factory()->managedBy($lucia)->create(['code' => 'LAB-316', 'name' => 'Laboratorio de Redes']);
    Space::factory()->office()->managedBy($lucia)->create(['code' => 'OF-SETI']);
    Space::factory()->managedBy($mario)->create(['code' => 'SAL-01']);
    Carbon::setTestNow();

    $section = byAdministratorSection(Livewire::test('pages::users.space-managers')->set('administrator', (string) $lucia->id)->html());

    expect($section)->toContain('LAB-316 · Laboratorio de Redes', 'Laboratorio', 'OF-SETI', 'Oficina', '15/10/2026 20:15')
        ->not->toContain('SAL-01');
});

test('an Administrador without spaces says so', function () {
    $admin = User::factory()->withRole(SystemRole::Administrador)->create();

    $section = byAdministratorSection(Livewire::test('pages::users.space-managers')->set('administrator', (string) $admin->id)->html());

    expect($section)->toContain(__('users.space_managers.no_spaces'));
});

test('only Administradores are offered, deactivated ones labelled', function () {
    User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Lucía Campos']);
    User::factory()->deactivated()->withRole(SystemRole::Administrador)->create(['name' => 'Ana Inactiva']);
    User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Rita Reservante']);

    $section = byAdministratorSection(Livewire::test('pages::users.space-managers')->html());

    expect($section)->toContain('Lucía Campos', 'Ana Inactiva', '('.__('users.status.deactivated').')')
        ->not->toContain('Rita Reservante');
});

test('deleted spaces are not listed as assigned', function () {
    $admin = User::factory()->withRole(SystemRole::Administrador)->create();
    $deleted = Space::factory()->managedBy($admin)->create(['code' => 'LAB-OLD']);
    $deleted->delete();

    $section = byAdministratorSection(Livewire::test('pages::users.space-managers')->set('administrator', (string) $admin->id)->html());

    expect($section)->toContain(__('users.space_managers.no_spaces'))->not->toContain('LAB-OLD');
});

test('the chosen Administrador is kept in the URL', function () {
    $admin = User::factory()->withRole(SystemRole::Administrador)->create();
    Space::factory()->managedBy($admin)->create(['code' => 'LAB-316']);

    $this->get(route('users.space-managers', ['administrador' => $admin->id]))->assertOk()->assertSee('LAB-316');
});

test('an edited URL pointing at someone who is not an Administrador is reported', function () {
    $reservante = User::factory()->withRole(SystemRole::Reservante)->create();

    Livewire::withQueryParams(['administrador' => $reservante->id])
        ->test('pages::users.space-managers')
        ->assertSee(__('users.space_managers.unknown_administrator'));
});

test('roles without spaces.assign_managers cannot consult the spaces of an Administrador', function () {
    $admin = actingAsRole(SystemRole::Administrador);

    $this->get(route('users.space-managers', ['administrador' => $admin->id]))->assertForbidden();
});
