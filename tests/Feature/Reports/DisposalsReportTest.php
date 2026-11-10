<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\User;
use Database\Factories\AssetEventFactory;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_76 · decommissions and losses with their date and description, filtered by event type and dates.

function disposalsReport(): ReportType
{
    return ReportType::where('code', 'disposals')->firstOrFail();
}

test('the disposals report tells decommissions from losses and shows their date, description and registrar', function () {
    actingAsRole(SystemRole::Reportes);
    $lost = Asset::factory()->create(['name' => 'Portátil perdido', 'status' => AssetStatus::Lost]);
    $retired = Asset::factory()->create(['name' => 'Monitor retirado', 'status' => AssetStatus::Decommissioned]);
    $registrar = User::factory()->create(['name' => 'Luis Registrador']);
    AssetEventFactory::new()->for($lost)->create(['occurred_on' => '2026-10-10', 'description' => 'Extraviado en el auditorio', 'registered_by' => $registrar->id]);
    AssetEventFactory::new()->for($retired)->decommission()->create(['occurred_on' => '2026-10-12', 'description' => 'Pantalla irreparable']);
    AssetEventFactory::new()->for($retired)->kind(AssetEventKind::Damage)->create(['description' => 'Golpe en la pantalla']);

    $this->get(route('reports.show', 'disposals'))->assertOk()
        ->assertSeeInOrder(['Pantalla irreparable', 'Extraviado en el auditorio'])
        ->assertSee($lost->code)->assertSee('Portátil perdido')->assertSee('Pérdida')->assertSee('Baja')
        ->assertSee('10/10/2026')->assertSee('Luis Registrador')->assertSee('Perdido')->assertSee('De baja')
        ->assertDontSee('Golpe en la pantalla');
});

test('a loss stays in the report after its asset is recovered and shows the current status', function () {
    actingAsRole(SystemRole::Reportes);
    $asset = Asset::factory()->create(['status' => AssetStatus::InService]);
    AssetEventFactory::new()->for($asset)->create(['occurred_on' => '2026-10-01', 'description' => 'Pérdida ya recuperada']);
    AssetEventFactory::new()->for($asset)->kind(AssetEventKind::Recovery)->create(['occurred_on' => '2026-10-03', 'description' => 'Apareció en bodega']);

    $this->get(route('reports.show', 'disposals'))->assertSee('Pérdida ya recuperada')->assertSee('En servicio')->assertDontSee('Apareció en bodega');
});

test('disposals can be filtered by event type and by inclusive event dates', function () {
    actingAsRole(SystemRole::Reportes);
    AssetEventFactory::new()->create(['occurred_on' => '2026-10-01', 'description' => 'Pérdida de octubre']);
    AssetEventFactory::new()->decommission()->create(['occurred_on' => '2026-10-31', 'description' => 'Baja de fin de mes']);
    AssetEventFactory::new()->create(['occurred_on' => '2026-09-30', 'description' => 'Pérdida de septiembre']);

    Livewire::test('pages::reports.show', ['report' => disposalsReport()])
        ->set('filters.kind.values', ['decommission'])
        ->assertSee('Baja de fin de mes')->assertDontSee('Pérdida de octubre')
        ->call('clearFilters')
        ->set('filters.occurred_on', ['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->assertSee('Pérdida de octubre')->assertSee('Baja de fin de mes')->assertDontSee('Pérdida de septiembre');
});

test('each disposals export keeps the selected type and writes the generation audit', function (string $format) {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    AssetEventFactory::new()->create(['description' => 'Pérdida exportada']);
    AssetEventFactory::new()->decommission()->create(['description' => 'Baja excluida']);

    $generation = app(ExportReport::class)->handle($author, 'disposals', ReportFormat::from($format), ['kind' => ['values' => ['loss']]]);

    expect($generation->row_count)->toBe(1)
        ->and(AuditLog::where('action', AuditAction::Generated)->sole()->entity_id)->toBe($generation->id);
    if ($format === 'xlsx') {
        $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
        expect($rows)->toHaveCount(1)->and($rows[0]['Tipo de evento'])->toBe('Pérdida')->and($rows[0]['Descripción'])->toBe('Pérdida exportada');
    }
})->with(['pdf', 'xlsx']);

test('invalid disposals filters produce Spanish feedback and no export', function (array $filters, string $message) {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    Livewire::test('pages::reports.show', ['report' => disposalsReport()])
        ->set('filters', $filters)->assertSee($message)->assertDontSee(__('reports.show.preview'));
    Livewire::test('reports.export-button', ['report' => 'disposals', 'filters' => $filters])->call('export', 'xlsx')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
})->with([
    'unknown key' => [['asset' => ['values' => [1]]], 'El filtro asset no está disponible.'],
    'damage is not a disposal' => [['kind' => ['values' => ['damage']]], 'Selecciona una opción válida del filtro.'],
    'bad date' => [['occurred_on' => ['to' => '2026-13-01']], 'Introduce una fecha válida con formato AAAA-MM-DD.'],
    'inverted range' => [['occurred_on' => ['from' => '2026-10-31', 'to' => '2026-10-01']], 'La fecha final no puede ser anterior a la inicial.'],
]);
