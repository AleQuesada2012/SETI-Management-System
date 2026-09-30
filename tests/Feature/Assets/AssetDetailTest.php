<?php

// RF_01 · Consultar información de activo, and RF_17 · Consultar garantía de activos, which closes with it.

use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Space;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('a Visualizador sees the information of an asset', function () {
    actingAsRole(SystemRole::Visualizador);
    $space = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110']);
    $custodian = userWithRole(SystemRole::Administrador);
    $asset = Asset::factory()->inSpace($space)->create([
        'name' => 'Monitor de la recepción',
        'brand_id' => Brand::query()->create(['name' => 'Dell'])->id,
        'model' => 'P2422H',
        'asset_tag' => 'TEC-10001',
        'location_detail' => 'Rack 2',
        'acquired_on' => '2025-03-14',
        'supplier_id' => Supplier::query()->create(['name' => 'Distribuidora Tecnológica S.A.'])->id,
        'responsible_user_id' => $custodian->id,
        'observations' => 'Tiene un rayón en el marco.',
    ]);

    $this->get(route('assets.show', $asset))
        ->assertOk()
        ->assertSee($asset->code)
        ->assertSee('Monitor de la recepción')
        ->assertSee('Cómputo')
        ->assertSee('Dell')
        ->assertSee('P2422H')
        ->assertSee('TEC-10001')
        ->assertSee('Buen estado')
        ->assertSee('LAB-110 · Laboratorio 110')
        ->assertSee('Rack 2')
        ->assertSee($custodian->name)
        ->assertSee('14/03/2025')
        ->assertSee('Distribuidora Tecnológica S.A.')
        ->assertSee('Tiene un rayón en el marco.');
});

test('the information shown is the current one in the database', function () {
    actingAsRole(SystemRole::Visualizador);
    $asset = Asset::factory()->inSpace(Space::factory()->create())->create(['model' => 'Modelo viejo']);

    $this->get(route('assets.show', $asset))->assertSee('Modelo viejo');

    $asset->update(['model' => 'Modelo nuevo']);

    $this->get(route('assets.show', $asset))->assertSee('Modelo nuevo')->assertDontSee('Modelo viejo');
});

test('an asset that does not exist answers with the not-found page', function () {
    actingAsRole(SystemRole::Visualizador);
    $deleted = Asset::factory()->inSpace(Space::factory()->create())->create();
    $deleted->delete();

    $this->get(route('assets.show', 999999))->assertNotFound()->assertSee(__('app.errors.404.title'));
    $this->get(route('assets.show', $deleted))->assertNotFound();
});

test('a Reservante cannot see assets', function () {
    actingAsRole(SystemRole::Reservante);
    $asset = Asset::factory()->inSpace(Space::factory()->create())->create();

    $this->get(route('assets.show', $asset))->assertForbidden();
});

test('the warranty shows whether the asset has one and whether it is still valid', function (?string $starts, ?string $ends, string $state) {
    actingAsRole(SystemRole::Visualizador);
    $asset = Asset::factory()->inSpace(Space::factory()->create())
        ->create(['warranty_starts_on' => $starts, 'warranty_ends_on' => $ends]);

    $response = $this->get(route('assets.show', $asset))
        ->assertOk()
        ->assertSee(__('assets.show.warranty_states.'.$state));

    if ($state === 'none') {
        $response->assertDontSee(Str::ucfirst(__('assets.fields.warranty_ends_on')));
    } else {
        $response->assertSee(Carbon::parse($ends)->format('d/m/Y'));
    }
})->with([
    'no warranty' => [null, null, 'none'],
    'a valid warranty' => [fn () => now()->subYear()->toDateString(), fn () => now()->addYear()->toDateString(), 'valid'],
    'an expired warranty' => ['2020-01-01', '2021-01-01', 'expired'],
]);
