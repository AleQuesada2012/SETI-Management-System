<?php

// RF_35 · Consultar información de un espacio.

use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Space;
use App\Models\User;

test('the space page shows its information, managers and assets', function () {
    $admin = userWithRole(SystemRole::Administrador);
    $space = Space::factory()->managedBy($admin)->create(['name' => 'Laboratorio 501', 'capacity' => 24, 'floor' => '5']);
    $ownAsset = Asset::factory()->inSpace($space)->create();
    $elsewhere = Asset::factory()->inSpace(Space::factory()->create())->create();

    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.show', $space))
        ->assertOk()
        ->assertSee('Laboratorio 501')
        ->assertSee($space->code)
        ->assertSee($space->spaceType->name)
        ->assertSee($space->status->name)
        ->assertSee($space->building->name)
        ->assertSee('Piso 5')
        ->assertSee('24 personas')
        ->assertSee($admin->name)
        ->assertSee($ownAsset->code)
        ->assertDontSee($elsewhere->code);
});

test('a space without assets or managers says so', function () {
    actingAsRole(SystemRole::Reservante);
    $space = Space::factory()->create();

    $this->get(route('spaces.show', $space))
        ->assertOk()
        ->assertSee(__('spaces.show.no_assets'))
        ->assertSee(__('spaces.show.no_managers'));
});

test('a space that does not exist answers with the not-found page', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.show', 999999))
        ->assertNotFound()
        ->assertSee(__('app.errors.404.title'));
});

test('a deleted space answers with the not-found page', function () {
    actingAsRole(SystemRole::Visualizador);
    $space = Space::factory()->create();
    $space->delete();

    $this->get(route('spaces.show', $space->id))->assertNotFound();
});

test('a user without permission to view spaces cannot open a space', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('spaces.show', Space::factory()->create()))->assertForbidden();
});
