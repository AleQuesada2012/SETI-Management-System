<?php

use App\Enums\SystemRole;
use App\Models\Asset;
use App\Queries\Reports\MaintenanceStatistics;
use Database\Factories\MaintenanceRecordFactory;
use Livewire\Livewire;

// RF_82 · maintenance and repairs performed in a period, and the assets with the most interventions.

test('the statistics count the maintenance and repairs performed and rank the assets by interventions', function () {
    actingAsRole(SystemRole::Reportes);
    $projector = Asset::factory()->create(['name' => 'Proyector intervenido']);
    $laptop = Asset::factory()->create(['name' => 'Portátil intervenido']);
    MaintenanceRecordFactory::new()->for($projector)->count(2)->create();
    MaintenanceRecordFactory::new()->for($projector)->repair()->create();
    MaintenanceRecordFactory::new()->for($laptop)->repair()->create();
    MaintenanceRecordFactory::new()->for($laptop)->scheduled()->create();

    $summary = MaintenanceStatistics::summary();

    expect($summary['total'])->toBe(4)
        ->and(array_column($summary['kinds'], 'count', 'id'))->toBe(['preventive' => 2, 'corrective' => 2])
        ->and(array_column($summary['assets'], 'count', 'id'))->toBe([$projector->id => 3, $laptop->id => 1])
        ->and($summary['assets'][0])->toMatchArray(['preventive' => 2, 'corrective' => 1, 'label' => $projector->code.' · Proyector intervenido']);
    $this->get(route('reports.statistics.maintenance'))->assertOk()
        ->assertSee('Intervenciones realizadas: 4')->assertSeeInOrder(['Proyector intervenido', 'Portátil intervenido']);
});

test('only the ten assets with the most interventions are ranked', function () {
    actingAsRole(SystemRole::Reportes);
    foreach (range(1, MaintenanceStatistics::TOP_ASSETS + 1) as $interventions) {
        MaintenanceRecordFactory::new()->for(Asset::factory())->count($interventions)->create();
    }

    $assets = MaintenanceStatistics::summary()['assets'];

    expect($assets)->toHaveCount(MaintenanceStatistics::TOP_ASSETS)
        ->and(array_column($assets, 'count'))->toBe(range(MaintenanceStatistics::TOP_ASSETS + 1, 2, -1));
});

test('the period filter includes both completion days and links each asset to its filtered report', function () {
    actingAsRole(SystemRole::Reportes);
    $asset = Asset::factory()->create();
    MaintenanceRecordFactory::new()->for($asset)->completedOn('2026-10-01')->create();
    MaintenanceRecordFactory::new()->for($asset)->repair()->completedOn('2026-10-31')->create();
    MaintenanceRecordFactory::new()->for($asset)->completedOn('2026-11-01')->create();
    $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];

    Livewire::test('pages::reports.maintenance-statistics')
        ->assertSee('Intervenciones realizadas: 3')
        ->set('filters.completed_on', $period)->assertSee('Intervenciones realizadas: 2')
        ->assertSee(route('reports.show', ['report' => 'maintenance', 'filters' => ['asset' => ['values' => [$asset->id]], 'completed_on' => $period]]))
        ->set('filters.completed_on', ['from' => '2026-12-01'])->assertSee(__('reports.maintenance_statistics.empty'))
        ->call('clearFilters')->assertSet('filters', [])->assertSee('Intervenciones realizadas: 3');
});

test('the counts follow new records on the next refresh', function () {
    actingAsRole(SystemRole::Reportes);
    $page = Livewire::test('pages::reports.maintenance-statistics')->assertSee(__('reports.maintenance_statistics.empty'));
    MaintenanceRecordFactory::new()->repair()->create();
    $page->call('$refresh')->assertSee('Intervenciones realizadas: 1');
});

test('invalid periods produce Spanish feedback instead of statistics', function (array $filters, string $message) {
    actingAsRole(SystemRole::Reportes);
    Livewire::test('pages::reports.maintenance-statistics')->set('filters', $filters)
        ->assertSee($message)->assertDontSee('Intervenciones realizadas');
})->with([
    'inverted range' => [['completed_on' => ['from' => '2026-10-31', 'to' => '2026-10-01']], 'La fecha final no puede ser anterior a la inicial.'],
    'bad date' => [['completed_on' => ['from' => '31/10/2026']], 'Introduce una fecha válida con formato AAAA-MM-DD.'],
    'unknown key' => [['asset' => ['values' => [1]]], 'El filtro asset no está disponible.'],
]);

test('maintenance statistics reject users without report permission', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.statistics.maintenance'))->assertForbidden();
});
