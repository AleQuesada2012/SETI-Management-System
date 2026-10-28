<?php

use App\Enums\SystemRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * RF_96 Consultar usuarios asignados a un laboratorio (any space type, ADR-0009).
 */

/** The HTML of the "Por espacio" section only: the "Por administrador" select below lists every Administrador. */
function bySpaceSection(string $html): string
{
    return Str::between($html, 'data-test="by-space"', 'data-test="by-administrator"');
}

test('the Superusuario picks a space and sees the Administradores in charge of it', function () {
    actingAsRole(SystemRole::Superusuario);
    $lucia = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Lucía Campos', 'email' => 'lcampos@tec.ac.cr']);
    $mario = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Mario Solís']);
    $other = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Otra Encargada']);

    Carbon::setTestNow('2026-10-14 09:30');
    $lab = Space::factory()->managedBy($lucia, $mario)->create(['code' => 'LAB-110']);
    Space::factory()->managedBy($other)->create(['code' => 'SAL-01']);
    Carbon::setTestNow();

    $this->get(route('users.space-managers'))->assertOk()->assertSee('LAB-110')->assertSee('SAL-01');

    $section = bySpaceSection(Livewire::test('pages::users.space-managers')->set('space', (string) $lab->id)->html());

    expect($section)->toContain('Lucía Campos', 'lcampos@tec.ac.cr', 'Mario Solís', '14/10/2026 09:30')
        ->not->toContain('Otra Encargada');
});

test('a space without managers says so', function () {
    actingAsRole(SystemRole::Superusuario);
    $room = Space::factory()->create();

    Livewire::test('pages::users.space-managers')
        ->set('space', (string) $room->id)
        ->assertSee(__('users.space_managers.none'));
});

test('until a space is chosen the select shows the selectable empty option, not the first space', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Lucía Campos']);
    $lab = Space::factory()->managedBy($admin)->create(['code' => 'LAB-110']);

    $this->get(route('users.space-managers'))
        ->assertOk()
        ->assertSeeInOrder([__('users.space_managers.choose'), 'LAB-110']);

    $page = Livewire::test('pages::users.space-managers');

    // The empty option must be selectable: a disabled one cannot stay selected and the browser shows the first space.
    expect($page->html())->toMatch('/<option[^>]*value=""/')
        ->not->toMatch('/<option[^>]*\bdisabled\b/');

    expect(bySpaceSection($page->html()))->not->toContain('Lucía Campos');
    expect(bySpaceSection($page->set('space', (string) $lab->id)->html()))->toContain('Lucía Campos');
    expect(bySpaceSection($page->set('space', '')->html()))->not->toContain('Lucía Campos')
        ->not->toContain(__('users.space_managers.unknown_space'));
});

test('the chosen space is kept in the URL', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Lucía Campos']);
    $lab = Space::factory()->managedBy($admin)->create();

    $this->get(route('users.space-managers', ['espacio' => $lab->id]))->assertOk()->assertSee('Lucía Campos');
});

test('deleted spaces are not offered, and an edited URL pointing at one is reported', function () {
    actingAsRole(SystemRole::Superusuario);
    $deleted = Space::factory()->create(['code' => 'LAB-OLD']);
    $deleted->delete();

    Livewire::withQueryParams(['espacio' => $deleted->id])
        ->test('pages::users.space-managers')
        ->assertDontSee('LAB-OLD')
        ->assertSee(__('users.space_managers.unknown_space'));
});

test('only the Superusuario sees the Encargados button and opens the page', function () {
    actingAsRole(SystemRole::Superusuario);
    $this->get(route('users.index'))->assertSee(route('users.space-managers'), escape: false);

    actingAsRole(SystemRole::Administrador);
    $this->get(route('users.index'))->assertDontSee(route('users.space-managers'), escape: false);
});

test('roles without spaces.assign_managers cannot consult the managers of a space', function (SystemRole $role) {
    actingAsRole($role);

    $this->get(route('users.space-managers'))->assertForbidden();
})->with([SystemRole::Administrador, SystemRole::Reportes, SystemRole::Visualizador, SystemRole::Reservante]);
