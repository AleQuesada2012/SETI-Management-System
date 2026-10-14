<?php

use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetType;
use App\Queries\Reports\AssetStatistics;
use Livewire\Livewire;

// RF_80 · distinct condition/lifecycle distributions.

test('statistics shows separate physical and lifecycle charts with accurate current totals', function () {
    actingAsRole(SystemRole::Reportes);
    Asset::factory()->count(2)->create();
    Asset::factory()->inCondition('Dañado')->create(['status' => AssetStatus::Lost]);
    $this->get(route('reports.statistics'))->assertOk()->assertSee('Condición física')->assertSee('Estado del ciclo de vida')
        ->assertSee('Total de activos: 3')->assertSee('Buen estado')->assertSee('Dañado')->assertSee('Perdido');
    $series = AssetStatistics::current();
    expect(array_column($series['condition'], 'count', 'id'))->toMatchArray([AssetCondition::where('name', 'Buen estado')->value('id') => 2, AssetCondition::where('name', 'Dañado')->value('id') => 1]);
    expect(array_column($series['lifecycle'], 'count', 'id'))->toMatchArray(['in_service' => 2, 'lost' => 1, 'in_maintenance' => 0]);
});

test('refresh reads updated asset conditions statuses and excludes deleted assets', function () {
    actingAsRole(SystemRole::Reportes);
    $asset = Asset::factory()->create();
    $page = Livewire::test('pages::reports.statistics')->assertSee('Total de activos: 1');
    $type = AssetType::query()->where('name', 'Silla')->firstOrFail();
    $asset->forceFill(['status' => AssetStatus::InMaintenance, 'asset_type_id' => $type->id, 'condition_id' => AssetCondition::where('name', 'Dañado')->value('id')])->save();
    $page->call('$refresh')->assertSee('En mantenimiento');
    $series = AssetStatistics::current();
    expect(array_column($series['lifecycle'], 'count', 'id')['in_maintenance'])->toBe(1);
    $asset->delete();
    $page->call('$refresh')->assertSee('Total de activos: 0')->assertSee('No hay activos registrados.');
});

test('statistics rejects users without report permission', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.statistics'))->assertForbidden();
    Livewire::test('pages::reports.statistics')->assertForbidden();
});
