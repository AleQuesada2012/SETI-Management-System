<?php

// RF_39 · Consulta del historial de un espacio.

use App\Actions\Spaces\UpdateSpace;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Queries\Spaces\SpaceHistory;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->admin->forceFill(['name' => 'Mario Solís'])->save();
    $this->space = Space::factory()->managedBy($this->admin)->create(['code' => 'LAB-700', 'name' => 'Laboratorio 700', 'capacity' => 30]);
});

test('the space page links to its history', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.show', $this->space))->assertOk()->assertSee(route('spaces.history', $this->space));
});

test('the history shows which Administrador or Superusuario changed the space, and what changed', function () {
    $this->actingAs($this->admin);
    app(UpdateSpace::class)->handle($this->admin, $this->space, [
        'code' => 'LAB-700', 'name' => 'Laboratorio 700', 'space_type_id' => $this->space->space_type_id,
        'building_id' => $this->space->building_id, 'capacity' => 42,
    ]);
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.history', $this->space))
        ->assertOk()
        ->assertSee('Mario Solís editó el espacio.')
        ->assertSee('Capacidad: 30 → 42');
});

test('lookup changes show names, not ids', function () {
    $otherBuilding = Building::factory()->create(['name' => 'Edificio Norte']);
    $before = $this->space->building->name;
    $this->actingAs($this->admin);

    app(UpdateSpace::class)->handle($this->admin, $this->space, [
        'code' => 'LAB-700', 'name' => 'Laboratorio 700', 'space_type_id' => $this->space->space_type_id,
        'building_id' => $otherBuilding->id, 'capacity' => 30,
    ]);
    $this->actingAs($this->admin);

    $this->get(route('spaces.history', $this->space))->assertSee("Edificio: {$before} → Edificio Norte");
});

test('the history shows when the space was reserved, for whom and by whom', function () {
    $responsible = User::factory()->create(['name' => 'Ana Rojas']);
    $creator = User::factory()->create(['name' => 'Luis Mora']);
    Reservation::factory()
        ->between(now()->addDays(3)->setTime(8, 0), now()->addDays(3)->setTime(10, 0))
        ->create(['space_id' => $this->space->id, 'title' => 'Clase de redes', 'responsible_id' => $responsible->id, 'created_by' => $creator->id]);
    actingAsRole(SystemRole::Reservante);

    $this->get(route('spaces.history', $this->space))
        ->assertOk()
        ->assertSee('Luis Mora reservó el espacio: «Clase de redes».')
        ->assertSee('de 08:00 a 10:00')
        ->assertSee('Para Ana Rojas.');
});

test('the history shows assets moved into the space', function () {
    $asset = Asset::factory()->inSpace($this->space)->create();
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.history', $this->space))->assertSee("registró la entrada del activo {$asset->refresh()->code}");
    expect(SpaceHistory::for($this->space)->getCollection()->pluck('kind'))->toContain('movement');
});

test('entries are newest first', function () {
    $this->travel(1)->days();
    app(UpdateSpace::class)->handle($this->admin, $this->space, [
        'code' => 'LAB-700', 'name' => 'Laboratorio 700 B', 'space_type_id' => $this->space->space_type_id,
        'building_id' => $this->space->building_id, 'capacity' => 30,
    ]);

    $kinds = SpaceHistory::for($this->space)->getCollection()->map(fn ($e) => json_decode($e->details, true)['action'] ?? $e->kind)->all();

    expect($kinds[0])->toBe('updated');
});

test('a space that has not changed since it was registered says its history is empty', function () {
    $quiet = Space::factory()->create();
    actingAsRole(SystemRole::Visualizador);

    Livewire::test('pages::spaces.history', ['space' => $quiet])->assertSee(__('spaces.history.empty'));

    Reservation::factory()->create(['space_id' => $quiet->id]);

    Livewire::test('pages::spaces.history', ['space' => $quiet])->assertDontSee(__('spaces.history.empty'));
});

test('a user who cannot view spaces cannot open a history', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('spaces.history', $this->space))->assertForbidden();
});
