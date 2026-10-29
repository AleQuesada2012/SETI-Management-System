<?php

// RF_74 · Exportar reporte de un espacio.

use App\Actions\Reports\ExportReport;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\ReportGeneration;
use App\Models\Space;
use App\Reports\ReportRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

beforeEach(function () {
    Storage::fake(config('filesystems.files_disk'));

    $this->author = userWithRole(SystemRole::Reportes);
    $this->manager = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->manager)->create(['code' => 'LAB-801', 'name' => 'Laboratorio 801', 'capacity' => 24, 'floor' => '3']);
    $this->assets = Asset::factory()->inSpace($this->space)->count(2)->create();
    $this->elsewhere = Asset::factory()->inSpace(Space::factory()->create())->create();
    $this->filters = ['space' => ['values' => [$this->space->id]]];
});

/**
 * @return array{info: array<string, string>, rows: list<array<string, mixed>>}
 */
function readSpaceReport(ReportGeneration $generation): array
{
    $local = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($local, Storage::disk($generation->file->disk)->get($generation->file->path));

    $rows = SimpleExcelReader::create($local)->getRows()->all();
    $info = SimpleExcelReader::create($local)->fromSheetName('Información')->noHeaderRow()->getRows()
        ->mapWithKeys(fn (array $row): array => [(string) $row[0] => (string) ($row[1] ?? '')])
        ->mapWithKeys(fn (string $value, string $label): array => [collect(['Espacio', 'Ubicación', 'Capacidad', 'Tipo de espacio', 'Estado operativo', 'Encargados del espacio'])
            ->first(fn (string $field): bool => $label === __('reports.document.filter', ['filter' => $field])) ?? $label => $value])
        ->all();
    @unlink($local);

    return ['info' => $info, 'rows' => $rows];
}

test('the report includes the space\'s name, location, capacity, type and status', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'space_detail', ReportFormat::Xlsx, $this->filters);

    $info = readSpaceReport($generation)['info'];

    expect($info)->toMatchArray([
        'Espacio' => 'LAB-801 · Laboratorio 801',
        'Capacidad' => '24 personas',
        'Tipo de espacio' => $this->space->spaceType->name,
        'Estado operativo' => $this->space->status->name,
        'Encargados del espacio' => $this->manager->name,
    ])->and($info['Ubicación'])->toContain('Piso 3');
});

test('the report lists the assets currently in the space with identification, name, brand, model and status', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'space_detail', ReportFormat::Xlsx, $this->filters);

    $rows = readSpaceReport($generation)['rows'];

    expect($generation->row_count)->toBe(2)
        ->and($rows)->toHaveCount(2)
        ->and($rows[0])->toHaveKeys(['Código de activo', 'Placa', 'Nombre', 'Marca', 'Modelo', 'Condición física', 'Estado del ciclo de vida'])
        ->and(collect($rows)->pluck('Código de activo')->sort()->values()->all())->toBe($this->assets->map(fn ($a) => $a->refresh()->code)->sort()->values()->all())
        ->and(collect($rows)->pluck('Código de activo'))->not->toContain($this->elsewhere->refresh()->code);
});

test('the report exports as PDF too', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'space_detail', ReportFormat::Pdf, $this->filters);

    expect($generation->file->mime_type)->toBe('application/pdf')
        ->and(Storage::disk($generation->file->disk)->get($generation->file->path))->toStartWith('%PDF');
});

test('exactly one existing space must be chosen', function (array $filters) {
    expect(fn () => app(ReportRegistry::class)->get('space_detail')->normalizeFilters($filters))
        ->toThrow(ValidationException::class);
})->with([
    'none' => [[]],
    'two' => fn () => [['space' => ['values' => [Space::factory()->create()->id, Space::factory()->create()->id]]]],
    'missing' => [['space' => ['values' => [999999]]]],
]);

test('the space page offers the export to users who may export reports', function () {
    $this->actingAs($this->author);

    $this->get(route('spaces.show', $this->space))->assertOk()->assertSeeHtml('data-test="export-button"');

    Livewire::test('reports.export-button', ['report' => 'space_detail', 'filters' => $this->filters])
        ->call('export', 'xlsx')
        ->assertHasNoErrors();

    expect(ReportGeneration::query()->sole()->filters)->toBe($this->filters);
});

test('users without permission to export reports cannot export a space\'s report', function (SystemRole $role) {
    $user = actingAsRole($role);

    $this->get(route('spaces.show', $this->space))->assertOk()->assertDontSeeHtml('data-test="export-button"');

    expect(fn () => app(ExportReport::class)->handle($user, 'space_detail', ReportFormat::Pdf, $this->filters))
        ->toThrow(AuthorizationException::class);
})->with([SystemRole::Administrador, SystemRole::Visualizador, SystemRole::Reservante]);
