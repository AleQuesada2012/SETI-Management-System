<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\Supplier;
use App\Queries\Reports\Warranties;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_75 · recorded warranty dates/provider, database validity and filtered exports.

test('the warranty report shows its supplier and dates and excludes assets without a warranty', function () {
    actingAsRole(SystemRole::Reportes);
    $supplier = Supplier::query()->create(['name' => 'Proveedor de garantía']);
    Asset::factory()->create(['name' => 'Activo con garantía', 'supplier_id' => $supplier->id,
        'warranty_starts_on' => '2000-01-01', 'warranty_ends_on' => '2099-12-31']);
    Asset::factory()->create(['name' => 'Activo sin garantía']);
    $this->get(route('reports.show', 'warranties'))->assertSee('Activo con garantía')->assertSee('Proveedor de garantía')
        ->assertSee('01/01/2000')->assertSee('31/12/2099')->assertDontSee('Activo sin garantía');
});

test('validity includes the final Costa Rica day and either or both states can be selected and cleared', function () {
    actingAsRole(SystemRole::Reportes);
    $today = DB::scalar("SELECT (now() AT TIME ZONE 'America/Costa_Rica')::date");
    Asset::factory()->create(['name' => 'Garantía termina hoy', 'warranty_starts_on' => '2000-01-01', 'warranty_ends_on' => $today]);
    Asset::factory()->create(['name' => 'Garantía vencida', 'warranty_starts_on' => '2000-01-01', 'warranty_ends_on' => '2001-01-01']);

    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'warranties')->firstOrFail()])
        ->set('filters.warranty_state.values', ['valid'])->assertSee('Garantía termina hoy')->assertDontSee('Garantía vencida')
        ->set('filters.warranty_state.values', ['expired'])->assertSee('Garantía vencida')->assertDontSee('Garantía termina hoy')
        ->set('filters.warranty_state.values', ['expired', 'valid', 'expired'])->assertSee('Garantía termina hoy')->assertSee('Garantía vencida')
        ->call('clearFilters')->assertSet('filters', []);
    expect(Warranties::validate(['warranty_state' => ['values' => ['expired', 'valid', 'expired']]]))
        ->toBe(['warranty_state' => ['values' => ['valid', 'expired']]]);
});

test('each warranty export keeps only selected records and writes the generation audit', function (string $format) {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    Asset::factory()->create(['name' => 'Garantía exportada', 'warranty_starts_on' => '2000-01-01', 'warranty_ends_on' => '2099-12-31']);
    Asset::factory()->create(['name' => 'Garantía excluida', 'warranty_starts_on' => '2000-01-01', 'warranty_ends_on' => '2001-01-01']);
    $generation = app(ExportReport::class)->handle($author, 'warranties', ReportFormat::from($format), ['warranty_state' => ['values' => ['valid']]]);
    expect($generation->row_count)->toBe(1)->and($generation->format)->toBe(ReportFormat::from($format));
    expect(AuditLog::where('action', AuditAction::Generated)->sole()->entity_id)->toBe($generation->id);
    if ($format === 'xlsx') {
        $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
        expect($rows)->toHaveCount(1)->and($rows[0]['Vigencia de la garantía'])->toBe('Vigente');
        expect($rows[0]['Nombre'])->toContain('Garantía exportada');
    }
})->with(['pdf', 'xlsx']);

test('invalid warranty filters produce Spanish feedback before an export exists', function (array $filters, string $message) {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'warranties')->firstOrFail()])
        ->set('filters', $filters)->assertSee($message)->assertDontSee(__('reports.show.preview'));
    Livewire::test('reports.export-button', ['report' => 'warranties', 'filters' => $filters])
        ->call('export', 'xlsx')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
})->with([
    'unknown key' => [['state' => 'valid'], 'El filtro state no está disponible.'],
    'unknown state' => [['warranty_state' => ['values' => ['none']]], 'Selecciona una opción válida del filtro.'],
    'scalar criterion' => [['warranty_state' => ''], 'La estructura del filtro no es válida.'],
    'scalar values' => [['warranty_state' => ['values' => '']], 'La estructura del filtro no es válida.'],
    'unknown member' => [['warranty_state' => ['extra' => true]], 'La estructura del filtro no es válida.'],
    'null value' => [['warranty_state' => ['values' => [null]]], 'Selecciona una opción válida del filtro.'],
]);

test('a user without report permission cannot consult warranties', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.show', 'warranties'))->assertForbidden();
});
