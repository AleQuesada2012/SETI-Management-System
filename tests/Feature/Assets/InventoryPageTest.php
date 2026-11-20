<?php

// RF_07 · Búsqueda de activos, RF_08 · Filtrado de activos por métricas and RF_30 · Consultar activos por
// responsable, on the inventory page (pages::assets.index).

use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Space;
use Livewire\Livewire;

beforeEach(function () {
    $this->viewer = actingAsRole(SystemRole::Visualizador);
    $this->lab = Space::factory()->create(['code' => 'LAB-1']);
    $this->office = Space::factory()->create(['code' => 'OF-1']);
    $this->monitor = Asset::factory()->inSpace($this->lab)->create(['name' => 'Monitor recepción']);
    $this->printer = Asset::factory()->inSpace($this->office)->create(['name' => 'Impresora oficina']);
});

test('a Visualizador sees the inventory', function () {
    $this->get(route('assets.index'))
        ->assertOk()
        ->assertSee('Monitor recepción')
        ->assertSee('Impresora oficina')
        ->assertSee('LAB-1');
});

test('the search shows the matching assets, or says the asset does not exist', function () {
    Livewire::test('pages::assets.index')
        ->set('search', 'recepcion')
        ->assertSee('Monitor recepción')
        ->assertDontSee('Impresora oficina')
        ->set('search', $this->printer->code)
        ->assertSee('Impresora oficina')
        ->assertDontSee('Monitor recepción')
        ->set('search', 'no existe')
        ->assertSee(__('assets.index.empty.search', ['term' => 'no existe']));
});

test('filters keep the matching assets, say when none match, and can be cleared', function () {
    Livewire::test('pages::assets.index')
        ->set('space', (string) $this->lab->id)
        ->assertSee('Monitor recepción')
        ->assertDontSee('Impresora oficina')
        ->set('lifecycle_status', 'lost')
        ->assertSee(__('assets.index.empty.filters'))
        ->call('clearFilters')
        ->assertSet('space', '')
        ->assertSet('lifecycle_status', '')
        ->assertSee('Monitor recepción')
        ->assertSee('Impresora oficina');
});

test('"Sin espacio" selects the assets without a space', function () {
    $unplaced = Asset::factory()->create(['name' => 'Laptop en tránsito']);

    Livewire::test('pages::assets.index')
        ->set('space', 'none')
        ->assertSee('Laptop en tránsito')
        ->assertDontSee('Monitor recepción');
});

test('the assets of a custodian are listed, or a message says they have none', function () {
    $custodian = userWithRole(SystemRole::Administrador);
    $withoutAssets = userWithRole(SystemRole::Administrador);
    $this->monitor->update(['responsible_user_id' => $custodian->id]);

    Livewire::test('pages::assets.index')
        ->set('responsible_user', (string) $custodian->id)
        ->assertSee('Monitor recepción')
        ->assertDontSee('Impresora oficina')
        ->set('responsible_user', (string) $withoutAssets->id)
        ->assertSee(__('assets.index.empty.responsible'));
});

test('an invalid date range is explained instead of listing (CP-08 c)', function () {
    Livewire::test('pages::assets.index')
        ->set('acquired_from', '2026-09-30')
        ->set('acquired_to', '2026-09-01')
        ->assertSee(__('assets.index.invalid'))
        ->assertSee(__('assets.filters.errors.range'))
        ->assertDontSee('Monitor recepción');

    $this->get(route('assets.index', ['acquired_from' => '2026-09-30', 'acquired_to' => '2026-09-01']))
        ->assertOk()
        ->assertSee(__('assets.filters.errors.range'));
});

test('an empty inventory says so', function () {
    Asset::query()->each(fn (Asset $asset) => $asset->delete());

    Livewire::test('pages::assets.index')->assertSee(__('assets.index.empty.inventory'));
});
