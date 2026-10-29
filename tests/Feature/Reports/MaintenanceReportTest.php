<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\User;
use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_66 · completed maintenance and repairs, filtered by asset, kind and completion dates.

function maintenanceReport(): ReportType
{
    return ReportType::where('code', 'maintenance')->firstOrFail();
}

test('the maintenance report identifies the asset, date, kind, description and who registered each completed record', function () {
    actingAsRole(SystemRole::Reportes);
    $asset = Asset::factory()->create(['name' => 'Proyector del aula']);
    $registrar = User::factory()->create(['name' => 'Ana Registradora']);
    MaintenanceRecordFactory::new()->for($asset)->completedOn('2026-10-05')
        ->create(['description' => 'Limpieza de filtros', 'performed_by' => 'Técnico externo', 'registered_by' => $registrar->id]);
    MaintenanceRecordFactory::new()->for($asset)->repair()->completedOn('2026-10-06')->create(['description' => 'Cambio de lámpara']);
    MaintenanceRecordFactory::new()->for($asset)->scheduled()->create(['description' => 'Revisión programada']);

    $this->get(route('reports.show', 'maintenance'))->assertOk()
        ->assertSeeInOrder(['Cambio de lámpara', 'Limpieza de filtros'])
        ->assertSee($asset->code)->assertSee('Proyector del aula')->assertSee('05/10/2026')
        ->assertSee('Mantenimiento')->assertSee('Reparación')->assertSee('Técnico externo')->assertSee('Ana Registradora')
        ->assertDontSee('Revisión programada');
});

test('maintenance can be filtered by asset, by kind and by inclusive completion dates', function () {
    actingAsRole(SystemRole::Reportes);
    $projector = Asset::factory()->create(['name' => 'Proyector']);
    $laptop = Asset::factory()->create(['name' => 'Portátil']);
    MaintenanceRecordFactory::new()->for($projector)->completedOn('2026-10-01')->create(['description' => 'Preventivo de octubre']);
    MaintenanceRecordFactory::new()->for($projector)->repair()->completedOn('2026-10-31')->create(['description' => 'Reparación de fin de mes']);
    MaintenanceRecordFactory::new()->for($laptop)->completedOn('2026-09-30')->create(['description' => 'Preventivo de septiembre']);

    Livewire::test('pages::reports.show', ['report' => maintenanceReport()])
        ->set('filters.asset.values', [(string) $projector->id])
        ->assertSee('Preventivo de octubre')->assertSee('Reparación de fin de mes')->assertDontSee('Preventivo de septiembre')
        ->set('filters.kind.values', ['corrective'])
        ->assertSee('Reparación de fin de mes')->assertDontSee('Preventivo de octubre')
        ->call('clearFilters')
        ->set('filters.completed_on', ['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->assertSee('Preventivo de octubre')->assertSee('Reparación de fin de mes')->assertDontSee('Preventivo de septiembre')
        ->assertSee('Fecha de realización: Desde 01/10/2026 · Hasta 31/10/2026');
});

test('each maintenance export keeps the selected kind and writes the generation audit', function (string $format) {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    MaintenanceRecordFactory::new()->repair()->create(['description' => 'Reparación exportada']);
    MaintenanceRecordFactory::new()->create(['description' => 'Mantenimiento excluido']);

    $generation = app(ExportReport::class)->handle($author, 'maintenance', ReportFormat::from($format), ['kind' => ['values' => ['corrective']]]);

    expect($generation->row_count)->toBe(1)
        ->and(AuditLog::where('action', AuditAction::Generated)->sole()->entity_id)->toBe($generation->id);
    if ($format === 'xlsx') {
        $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
        expect($rows)->toHaveCount(1)->and($rows[0]['Tipo de intervención'])->toBe('Reparación')
            ->and($rows[0]['Descripción'])->toBe('Reparación exportada');
    }
})->with(['pdf', 'xlsx']);

test('invalid maintenance filters produce Spanish feedback and no export', function (array $filters, string $message) {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    Livewire::test('pages::reports.show', ['report' => maintenanceReport()])
        ->set('filters', $filters)->assertSee($message)->assertDontSee(__('reports.show.preview'));
    Livewire::test('reports.export-button', ['report' => 'maintenance', 'filters' => $filters])->call('export', 'xlsx')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
})->with([
    'unknown key' => [['status' => ['values' => ['completed']]], 'El filtro status no está disponible.'],
    'unknown kind' => [['kind' => ['values' => ['inspection']]], 'Selecciona una opción válida del filtro.'],
    'missing asset' => [['asset' => ['values' => [999999]]], 'Selecciona una opción válida del filtro.'],
    'bad date' => [['completed_on' => ['from' => '01/10/2026']], 'Introduce una fecha válida con formato AAAA-MM-DD.'],
    'inverted range' => [['completed_on' => ['from' => '2026-10-31', 'to' => '2026-10-01']], 'La fecha final no puede ser anterior a la inicial.'],
    'scalar criterion' => [['kind' => 'corrective'], 'La estructura del filtro no es válida.'],
]);

test('a user without report permission cannot consult maintenance', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.show', 'maintenance'))->assertForbidden();
});
