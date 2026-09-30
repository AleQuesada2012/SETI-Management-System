<?php

// RF_42 · Búsqueda de espacios.

use App\Enums\SystemRole;
use App\Models\Building;
use App\Models\Space;
use App\Models\User;
use App\Queries\Spaces\SpaceDirectory;
use Livewire\Livewire;

beforeEach(function () {
    $north = Building::factory()->create(['name' => 'Edificio Norte']);
    $south = Building::factory()->create(['name' => 'Edificio Sur']);

    $this->lab = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio de Electrónica', 'building_id' => $north->id, 'floor' => '1']);
    $this->meeting = Space::factory()->create(['code' => 'SAL-02', 'name' => 'Salón de reuniones', 'building_id' => $south->id, 'location_detail' => 'Ala oeste']);
    $this->office = Space::factory()->create(['code' => 'OF-7', 'name' => 'Oficina de coordinación', 'building_id' => $south->id]);
});

/** @return list<string> */
function searchCodes(string $term): array
{
    return SpaceDirectory::query(['search' => $term])->pluck('code')->all();
}

test('the search matches the identifier, the name and the location', function () {
    expect(searchCodes('lab-110'))->toBe(['LAB-110'])
        ->and(searchCodes('reuniones'))->toBe(['SAL-02'])
        ->and(searchCodes('ala oeste'))->toBe(['SAL-02'])
        ->and(searchCodes('edificio sur'))->toBe(['OF-7', 'SAL-02']);
});

test('the search is partial and ignores case and accents', function () {
    expect(searchCodes('ELECTRONICA'))->toBe(['LAB-110'])
        ->and(searchCodes('salon'))->toBe(['SAL-02'])
        ->and(searchCodes('coordina'))->toBe(['OF-7']);
});

test('wildcards typed by the user are taken literally', function () {
    expect(searchCodes('%'))->toBe([])
        ->and(searchCodes('_'))->toBe([]);
});

test('a blank search leaves the list unfiltered', function () {
    expect(searchCodes('   '))->toHaveCount(3);
});

test('the search box shows the matching spaces', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::test('spaces.space-search')
        ->set('search', 'salon')
        ->assertSee('SAL-02')
        ->assertSee('Salón de reuniones')
        ->assertSee('1 espacio coincide con la búsqueda.')
        ->assertDontSee('LAB-110');
});

test('when nothing matches the user is told no space exists with those conditions', function () {
    actingAsRole(SystemRole::Visualizador);

    Livewire::test('spaces.space-search')
        ->set('search', 'piscina')
        ->assertSee('No existe ningún espacio que coincida con');
});

test('the search is kept in the URL of the spaces list', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.index', ['buscar' => 'oficina']))
        ->assertOk()
        ->assertSee('OF-7')
        ->assertDontSee('SAL-02');
});

test('a user without permission to view spaces cannot search them', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('spaces.index', ['buscar' => 'lab']))->assertForbidden();
});
