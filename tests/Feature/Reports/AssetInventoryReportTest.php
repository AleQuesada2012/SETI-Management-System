<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\Space;
use App\Models\User;
use App\Reports\Definitions\AssetInventoryReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

test('inventory preview separates condition and lifecycle and shows every required field', function () {
    actingAsRole(SystemRole::Reportes);
    $manager = User::factory()->create(['name' => 'Ana Responsable']);
    $space = Space::factory()->create(['name' => 'Laboratorio Norte']);
    Asset::factory()->inSpace($space)->create(['name' => 'Monitor de prueba', 'model' => 'Modelo Demo',
        'brand_id' => Brand::factory()->create(['name' => 'Marca Demo'])->id,
        'responsible_user_id' => $manager->id, 'acquired_on' => '2026-09-30']);
    $this->get(route('reports.show', 'assets_inventory'))->assertOk()
        ->assertSee('Condición física')->assertSee('Estado del ciclo de vida')->assertSee('Buen estado')
        ->assertSee('En servicio')->assertSee('Ana Responsable')->assertSee('Laboratorio Norte')
        ->assertSee('Marca Demo')->assertSee('Modelo Demo')->assertSee('30/09/2026');
});

test('the inventory report labels and applies the shared category filter', function () {
    actingAsRole(SystemRole::Reportes);
    $category = AssetCategory::query()->where('name', 'Cómputo')->firstOrFail();
    Asset::factory()->ofType('Monitor')->create(['name' => 'Monitor de categoría']);
    Asset::factory()->ofType('Escritorio')->create(['name' => 'Escritorio de otra categoría']);
    $report = ReportType::query()->where('code', 'assets_inventory')->firstOrFail();

    Livewire::test('pages::reports.show', ['report' => $report])
        ->assertSee('Categoría')
        ->assertSee('Monitor de categoría')
        ->assertSee('Escritorio de otra categoría')
        ->set('filters.category.values', [(string) $category->id])
        ->assertSee('Monitor de categoría')
        ->assertDontSee('Escritorio de otra categoría');
});

test('invalid inventory input is rejected before an export is recorded', function (array $filters) {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    expect(fn () => app(ExportReport::class)->handle($author, 'assets_inventory', ReportFormat::Pdf, $filters))
        ->toThrow(ValidationException::class);
    expect(ReportGeneration::query()->exists())->toBeFalse()
        ->and(Storage::disk(config('filesystems.files_disk'))->allFiles())->toBe([]);
})->with([
    'unknown criterion' => [['space_id' => 1]],
    'unknown member' => [['brand' => ['other' => 1]]],
    'invalid shape' => [['brand' => 'x']],
    'invalid text' => [['search' => ['x']]],
    'unknown reference' => [['brand' => ['values' => [999999]]]],
    'invalid color' => [['condition_color' => ['values' => ['gray']]]],
    'invalid lifecycle' => [['lifecycle_status' => ['values' => ['available']]]],
    'invalid missing selection' => [['brand' => ['include_missing' => 'maybe']]],
    'missing not supported' => [['condition' => ['include_missing' => true]]],
    'invalid date' => [['acquired_on' => ['from' => '2026-02-30']]],
    'inverted range' => [['acquired_on' => ['from' => '2026-09-30', 'to' => '2026-09-01']]],
]);

test('invalid filters show Spanish feedback and hide preview and export until cleared', function () {
    actingAsRole(SystemRole::Reportes);
    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'assets_inventory')->firstOrFail()])
        ->set('filters', ['brand' => ['values' => [999999]]])
        ->assertSee(__('reports.filters.errors.value'))->assertDontSee(__('reports.show.preview'))
        ->assertDontSee('data-test="export-button"', false)
        ->call('clearFilters')->assertSet('filters', [])->assertSee(__('reports.show.preview'));
});

test('inventory export uses all filtered rows beyond the preview and records normalized filters and audit', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $brand = Brand::factory()->create(['name' => 'Marca Exportada']);
    Asset::factory()->count(51)->create(['brand_id' => $brand->id]);
    Asset::factory()->create(['name' => 'No exportar']);
    $filters = ['brand' => ['values' => [$brand->id, (string) $brand->id]]];
    $definition = app(AssetInventoryReport::class);
    $normalized = $definition->normalizeFilters($filters);
    $generation = app(ExportReport::class)->handle($author, 'assets_inventory', ReportFormat::Xlsx, $filters);
    $path = Storage::disk($generation->file->disk)->path($generation->file->path);
    $rows = SimpleExcelReader::create($path)->getRows()->all();
    expect($rows)->toHaveCount(51)
        ->and($rows[0])->toHaveKeys(array_values($definition->columns()))
        ->and($generation->filters)->toBe($normalized)
        ->and($generation->row_count)->toBe(51);
    expect(AuditLog::query()->where('action', AuditAction::Generated)->sole()->new_values['filters'])->toBe($normalized);
    $this->get(route('reports.show', ['report' => 'assets_inventory', 'filters' => $filters]))
        ->assertOk()->assertSee('51 registros')->assertDontSee('No exportar');
});
