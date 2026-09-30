<?php

// RF_53 · Consultar listado de espacios · RF_54 · Filtrar espacios por atributos.

use App\Enums\SystemRole;
use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use App\Models\User;
use App\Queries\Spaces\SpaceDirectory;
use Livewire\Livewire;

beforeEach(function () {
    $this->building = Building::factory()->create(['name' => 'Edificio B']);

    $this->lab = Space::factory()->create([
        'code' => 'LAB-110', 'name' => 'Laboratorio 110', 'building_id' => $this->building->id,
        'floor' => '2', 'location_detail' => 'Ala norte', 'capacity' => 30,
    ]);
    $this->labInMaintenance = Space::factory()->withStatus('En mantenimiento')->create([
        'code' => 'LAB-120', 'name' => 'Laboratorio 120', 'capacity' => 20,
    ]);
    $this->office = Space::factory()->office()->create([
        'code' => 'OF-1', 'name' => 'Oficina de SETI', 'capacity' => 4,
    ]);
});

test('every role that may see spaces gets the list with name, location, capacity, type and status', function (SystemRole $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('spaces.index'))
        ->assertOk()
        ->assertSeeInOrder(['Laboratorio 110', 'LAB-110', 'Edificio B · Piso 2 · Ala norte', '30', 'Laboratorio', 'Disponible'])
        ->assertSee('Laboratorio 120')
        ->assertSee('En mantenimiento')
        ->assertSee('Oficina de SETI')
        ->assertSee(route('spaces.show', $this->lab), escape: false);
})->with([SystemRole::Superusuario, SystemRole::Administrador, SystemRole::Reportes, SystemRole::Visualizador, SystemRole::Reservante]);

test('deleted spaces are not listed', function () {
    $this->lab->delete();

    $this->actingAs(userWithRole(SystemRole::Visualizador))
        ->get(route('spaces.index'))
        ->assertOk()
        ->assertDontSee('Laboratorio 110')
        ->assertSee('Laboratorio 120');
});

test('a user without spaces.view cannot open the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('spaces.index'))
        ->assertForbidden();
});

test('spaces are filtered by type and by status, and both criteria combine', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));
    $laboratorio = SpaceType::query()->where('name', 'Laboratorio')->value('id');
    $disponible = SpaceStatus::query()->where('name', 'Disponible')->value('id');

    Livewire::test('pages::spaces.index')
        ->set('filters.space_type_id', (string) $laboratorio)
        ->assertSee('Laboratorio 110')
        ->assertSee('Laboratorio 120')
        ->assertDontSee('Oficina de SETI')
        ->set('filters.space_status_id', (string) $disponible)
        ->assertSee('Laboratorio 110')
        ->assertDontSee('Laboratorio 120')
        ->assertDontSee('Oficina de SETI')
        ->assertSee(trans_choice('spaces.index.filters.active', 2, ['count' => 2]));
});

test('spaces are filtered by building and minimum capacity', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));

    Livewire::test('pages::spaces.index')
        ->set('filters.building_id', (string) $this->building->id)
        ->assertSee('Laboratorio 110')
        ->assertDontSee('Laboratorio 120')
        ->set('filters', ['min_capacity' => '20'])
        ->assertSee('Laboratorio 110')
        ->assertSee('Laboratorio 120')
        ->assertDontSee('Oficina de SETI');
});

test('when no space matches the list says so, and clearing the filters restores it', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));
    $oficina = SpaceType::query()->where('name', 'Oficina')->value('id');
    $enMantenimiento = SpaceStatus::query()->where('name', 'En mantenimiento')->value('id');

    Livewire::test('pages::spaces.index')
        ->set('filters', ['space_type_id' => (string) $oficina, 'space_status_id' => (string) $enMantenimiento])
        ->assertSee(__('spaces.index.no_matches'))
        ->assertDontSee('Laboratorio 110')
        ->call('clearFilters')
        ->assertSet('filters', [])
        ->assertSee('Laboratorio 110')
        ->assertSee('Laboratorio 120')
        ->assertSee('Oficina de SETI');
});

test('the directory ignores blank and malformed filter values', function () {
    expect(SpaceDirectory::query(['space_type_id' => '', 'space_status_id' => 'abc', 'min_capacity' => '-3'])->count())->toBe(3)
        ->and(SpaceDirectory::query(['min_capacity' => 25])->pluck('code')->all())->toBe(['LAB-110']);
});
