<?php

// RF_53 · Consultar listado de espacios · RF_54 · Filtrar espacios por atributos (spaces filter contract).

use App\Enums\SystemRole;
use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use App\Models\User;
use App\Queries\Spaces\SpaceDirectory;
use Illuminate\Validation\ValidationException;
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

    $this->laboratorio = SpaceType::query()->where('name', 'Laboratorio')->value('id');
    $this->oficina = SpaceType::query()->where('name', 'Oficina')->value('id');
    $this->disponible = SpaceStatus::query()->where('name', 'Disponible')->value('id');
    $this->enMantenimiento = SpaceStatus::query()->where('name', 'En mantenimiento')->value('id');
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
        ->assertDontSee('LAB-110')
        ->assertSee('Laboratorio 120');
});

test('a user without spaces.view cannot open the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('spaces.index'))
        ->assertForbidden();
});

test('several values of one criterion combine with OR, and criteria combine with AND', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));

    Livewire::test('pages::spaces.index')
        ->set('filters.space_type.values', [(string) $this->laboratorio, (string) $this->oficina])
        ->assertSee('LAB-110')
        ->assertSee('LAB-120')
        ->assertSee('OF-1')
        ->set('filters.space_status.values', [(string) $this->disponible])
        ->assertSee('LAB-110')
        ->assertSee('OF-1')
        ->assertDontSee('LAB-120')
        ->assertSee('Tipo de espacio: Laboratorio, Oficina')
        ->assertSee('Estado: Disponible');
});

test('the search box narrows the list and combines with the filters (AND)', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));

    Livewire::test('pages::spaces.index')
        ->dispatch('space-search-updated', search: 'laboratorio')
        ->assertSet('search', 'laboratorio')
        ->assertSee('LAB-110')
        ->assertSee('LAB-120')
        ->assertDontSee('OF-1')
        ->set('filters.space_status.values', [(string) $this->disponible])
        ->assertSee('LAB-110')
        ->assertDontSee('LAB-120');

    $this->get(route('spaces.index', ['buscar' => 'oficina']))
        ->assertOk()
        ->assertSee('OF-1')
        ->assertDontSee('LAB-110');
});

test('spaces are filtered by building', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));

    Livewire::test('pages::spaces.index')
        ->set('filters.building.values', [(string) $this->building->id])
        ->assertSee('LAB-110')
        ->assertDontSee('LAB-120')
        ->assertDontSee('OF-1');
});

test('when no space matches the list says so, and clearing the filters restores it', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));

    Livewire::test('pages::spaces.index')
        ->set('filters', ['space_type' => ['values' => [(string) $this->oficina]], 'space_status' => ['values' => [(string) $this->enMantenimiento]]])
        ->assertSee(__('spaces.index.no_matches'))
        ->assertDontSee('LAB-110')
        ->call('clearFilters')
        ->assertSet('filters', [])
        ->assertSee('LAB-110')
        ->assertSee('LAB-120')
        ->assertSee('OF-1');
});

test('invalid filters block the list with Spanish messages', function (array $filters, string $message) {
    $this->actingAs(userWithRole(SystemRole::Visualizador));

    Livewire::withQueryParams(['filters' => $filters])
        ->test('pages::spaces.index')
        ->assertSee(__('spaces.index.invalid_filters'))
        ->assertSee($message)
        ->assertDontSee('LAB-110');
})->with([
    'unknown key' => [['capacity' => ['values' => [10]]], 'El filtro «capacity» no existe.'],
    'not an object' => [['building' => '3'], 'El filtro «Edificio» no tiene un formato válido.'],
    'unsupported member' => [['building' => ['values' => [1], 'include_missing' => true]], 'El filtro «Edificio» no tiene un formato válido.'],
    'id that never existed' => [['space_type' => ['values' => [999999]]], 'El filtro «Tipo de espacio» tiene una opción que no existe.'],
]);

test('a removed catalog entry that a space still uses stays a labelled, valid choice', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador));
    $this->building->delete();

    expect(collect(SpaceDirectory::choices()['building'])->firstWhere('id', $this->building->id))
        ->toBe(['id' => $this->building->id, 'label' => 'Edificio B (eliminado)', 'removed' => true]);

    Livewire::test('pages::spaces.index')
        ->set('filters.building.values', [(string) $this->building->id])
        ->assertDontSee(__('spaces.index.invalid_filters'))
        ->assertSee('LAB-110')
        ->assertDontSee('LAB-120');
});

test('the contract normalizes filters: empty criteria and duplicate selections are dropped', function () {
    expect(SpaceDirectory::validate([
        'search' => '  ',
        'space_type' => ['values' => [(string) $this->oficina, $this->oficina, (string) $this->laboratorio]],
        'space_status' => ['values' => []],
        'building' => [],
    ]))->toBe(['space_type' => ['values' => collect([$this->laboratorio, $this->oficina])->sort()->values()->all()]])
        ->and(SpaceDirectory::validate([]))->toBe([]);

    expect(fn () => SpaceDirectory::validate(['space_status' => ['values' => ['abc']]]))
        ->toThrow(ValidationException::class, 'El filtro «Estado» tiene una opción que no existe.');
});
