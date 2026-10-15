<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\Space;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_62, RF_70, RF_71, RF_72 · shared preview filters, successful exports and generation context.

test('report criteria combine, describe the selection and clearing restores all rows and the first page', function () {
    actingAsRole(SystemRole::Reportes);
    $brands = Brand::factory()->count(2)->create();
    $space = Space::factory()->create();
    Asset::factory()->inSpace($space)->create(['name' => 'Reporte coincidente', 'brand_id' => $brands[0]->id]);
    Asset::factory()->inSpace($space)->create(['name' => 'Reporte alternativa', 'brand_id' => $brands[1]->id]);
    Asset::factory()->inSpace(Space::factory()->create())->create(['name' => 'Reporte otra ubicación', 'brand_id' => $brands[0]->id]);

    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'assets_inventory')->firstOrFail()])
        ->set('filters.brand.values', $brands->pluck('id')->all())
        ->set('filters.space.values', [$space->id])
        ->assertSee('Reporte coincidente')
        ->assertSee('Reporte alternativa')
        ->assertDontSee('Reporte otra ubicación')
        ->assertSee(__('reports.document.filters'))
        ->call('setPage', 2)
        ->call('clearFilters')
        ->assertSet('filters', [])
        ->assertSet('paginators.page', 1)
        ->assertSee('Reporte otra ubicación')
        ->assertSee(__('reports.document.all_records'));
});

test('exporting either format confirms success and records exactly the selected report and filters', function (string $format) {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $brand = Brand::factory()->create(['name' => 'Marca seleccionada']);
    Asset::factory()->create(['name' => 'Activo exportado', 'brand_id' => $brand->id]);
    Asset::factory()->create(['name' => 'Activo excluido']);

    Livewire::test('reports.export-button', [
        'report' => 'assets_inventory', 'filters' => ['brand' => ['values' => [$brand->id, (string) $brand->id]]],
    ])->call('export', $format)
        ->assertFileDownloaded()
        ->assertDispatched('toast-show', slots: ['text' => __('reports.export.done')]);

    $generation = ReportGeneration::query()->sole();
    expect($generation->format)->toBe(ReportFormat::from($format))
        ->and($generation->row_count)->toBe(1)
        ->and($generation->generated_by)->toBe($author->id)
        ->and($generation->filters)->toBe(['brand' => ['values' => [$brand->id]]]);
    expect(AuditLog::where('action', AuditAction::Generated)->sole()->new_values)
        ->toMatchArray(['report' => 'assets_inventory', 'format' => $format, 'row_count' => 1]);
})->with(['pdf', 'xlsx']);

test('XLSX generation information gives the fixed time and readable filters or all-records statement', function (bool $filtered) {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(17, 0));
    $brand = Brand::factory()->create(['name' => 'Marca contextual']);
    Asset::factory()->create(['name' => 'Activo contextual', 'brand_id' => $brand->id]);
    $filters = $filtered ? ['brand' => ['values' => [$brand->id]]] : [];

    $generation = app(ExportReport::class)->handle($author, 'assets_inventory', ReportFormat::Xlsx, $filters);
    $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))
        ->fromSheetName('Información')->noHeaderRow()->getRows()->all();

    expect($rows)->toContain(['Generado el', '14/10/2026 17:00'])
        ->toContain(['Generado por', $author->name])
        ->toContain($filtered ? ['Filtro: Marca', 'Marca contextual'] : ['Filtros aplicados', 'ninguno (contiene la totalidad de los registros)']);
})->with(['with filters' => [true], 'without filters' => [false]]);

test('an invalid export format gives Spanish feedback and creates no generation, audit or file', function (string $format) {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));

    Livewire::test('reports.export-button', ['report' => 'assets_inventory'])
        ->call('export', $format)->assertHasErrors(['format'])->assertSee(__('reports.export.invalid_format'));

    expect(ReportGeneration::query()->exists())->toBeFalse()
        ->and(AuditLog::where('action', AuditAction::Generated)->exists())->toBeFalse()
        ->and(Storage::disk(config('filesystems.files_disk'))->allFiles())->toBe([]);
})->with(['csv', '', 'PDF']);

test('calling the hidden export action cannot bypass its permission', function () {
    actingAsRole(SystemRole::Visualizador);
    Storage::fake(config('filesystems.files_disk'));

    Livewire::test('reports.export-button', ['report' => 'assets_inventory'])
        ->assertDontSee('data-test="export-button"', false)
        ->call('export', 'pdf')->assertForbidden();

    expect(ReportGeneration::query()->exists())->toBeFalse()
        ->and(Storage::disk(config('filesystems.files_disk'))->allFiles())->toBe([]);
});
