<?php

use App\Actions\Assets\AddAssetComponent;
use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use App\Models\Reporting\AssetRow;
use App\Models\ReportType;
use App\Models\Space;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_77 · registered components next to their asset, filtered by asset.

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
});

function componentsReport(): ReportType
{
    return ReportType::where('code', 'components')->firstOrFail();
}

/** @param  array<string, mixed>  $input */
function addComponent(Asset $asset, array $input): void
{
    app(AddAssetComponent::class)->handle(test()->admin, $asset, $input + ['quantity' => 1]);
}

test('the components report shows every registered component under the asset it belongs to', function () {
    $computer = Asset::factory()->inSpace($this->space)->create(['name' => 'Computadora de laboratorio']);
    addComponent($computer, ['name' => 'Memoria RAM', 'specs' => '16 GB DDR4', 'serial_number' => 'RAM-123', 'quantity' => 2, 'notes' => 'Ampliada en 2026']);
    addComponent($computer, ['name' => 'Disco SSD', 'specs' => '512 GB NVMe']);
    Asset::factory()->inSpace($this->space)->create(['name' => 'Silla sin componentes']);
    actingAsRole(SystemRole::Reportes);

    $this->get(route('reports.show', 'components'))->assertOk()
        ->assertSeeInOrder(['Disco SSD', 'Memoria RAM'])
        ->assertSee($computer->code)->assertSee('Computadora de laboratorio')->assertSee('16 GB DDR4')->assertSee('RAM-123')
        ->assertSee('Ampliada en 2026')->assertDontSee('Silla sin componentes');
});

test('an asset without a name of its own is named as the inventory names it, in the rows and the filter', function () {
    $unnamed = Asset::factory()->inSpace($this->space)->create(['name' => null]);
    addComponent($unnamed, ['name' => 'Ventilador']);
    actingAsRole(SystemRole::Reportes);
    $displayName = AssetRow::query()->findOrFail($unnamed->id)->display_name;

    $this->get(route('reports.show', 'components'))->assertSee($unnamed->code.' · '.$displayName)
        ->assertSeeInOrder([$unnamed->code, $displayName, 'Ventilador']);
});

test('components can be filtered by asset, and those of deleted assets are not reported', function () {
    $first = Asset::factory()->inSpace($this->space)->create();
    $second = Asset::factory()->inSpace($this->space)->create();
    $deleted = Asset::factory()->inSpace($this->space)->create();
    addComponent($first, ['name' => 'Tarjeta de red']);
    addComponent($second, ['name' => 'Fuente de poder']);
    addComponent($deleted, ['name' => 'Componente de activo eliminado']);
    $deleted->delete();
    actingAsRole(SystemRole::Reportes);

    Livewire::test('pages::reports.show', ['report' => componentsReport()])
        ->assertSee('Tarjeta de red')->assertSee('Fuente de poder')->assertDontSee('Componente de activo eliminado')
        ->set('filters.asset.values', [(string) $first->id])
        ->assertSee('Tarjeta de red')->assertDontSee('Fuente de poder');
});

test('each components export keeps the selected asset and writes the generation audit', function (string $format) {
    $first = Asset::factory()->inSpace($this->space)->create();
    addComponent($first, ['name' => 'Componente exportado']);
    addComponent(Asset::factory()->inSpace($this->space)->create(), ['name' => 'Componente excluido']);
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));

    $generation = app(ExportReport::class)->handle($author, 'components', ReportFormat::from($format), ['asset' => ['values' => [$first->id]]]);

    expect($generation->row_count)->toBe(1)
        ->and(AuditLog::where('action', AuditAction::Generated)->sole()->entity_id)->toBe($generation->id);
    if ($format === 'xlsx') {
        $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
        expect($rows)->toHaveCount(1)->and($rows[0]['Componente'])->toBe('Componente exportado')->and($rows[0]['Código de activo'])->toBe($first->code);
    }
})->with(['pdf', 'xlsx']);

test('invalid component filters produce Spanish feedback and no export', function (array $filters, string $message) {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    Livewire::test('pages::reports.show', ['report' => componentsReport()])
        ->set('filters', $filters)->assertSee($message)->assertDontSee(__('reports.show.preview'));
    Livewire::test('reports.export-button', ['report' => 'components', 'filters' => $filters])->call('export', 'xlsx')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
})->with([
    'unknown key' => [['name' => 'RAM'], 'El filtro name no está disponible.'],
    'missing asset' => [['asset' => ['values' => [999999]]], 'Selecciona una opción válida del filtro.'],
    'text asset' => [['asset' => ['values' => ['abc']]], 'Selecciona una opción válida del filtro.'],
    'unknown member' => [['asset' => ['contains' => 'PC']], 'La estructura del filtro no es válida.'],
]);

test('a user without report permission cannot consult components', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.show', 'components'))->assertForbidden();
});
