<?php

use App\Actions\Assets\RecordAssetMovement;
use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_67 · inventory movements by asset, type and Costa Rica dates, with each asset's history in order.

beforeEach(function () {
    $this->admin = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Admin Traslados']);
    $this->origin = Space::factory()->managedBy($this->admin)->create(['code' => 'LAB-101', 'name' => 'Laboratorio de redes']);
    $this->destination = Space::factory()->managedBy($this->admin)->create(['code' => 'LAB-202', 'name' => 'Laboratorio de electrónica']);
});

function movementsReport(): ReportType
{
    return ReportType::where('code', 'movements')->firstOrFail();
}

function transfer(Asset $asset, Space $to, string $movedAt, string $reason): void
{
    app(RecordAssetMovement::class)->handle(test()->admin, $asset->refresh(), ['type' => 'transfer', 'to_space_id' => $to->id,
        'moved_at' => $movedAt, 'reason' => $reason]);
}

test('the movements report shows each asset, type, date and user, with every asset history in chronological order', function () {
    $first = Asset::factory()->inSpace($this->origin)->create(['name' => 'Router principal']);
    $second = Asset::factory()->inSpace($this->origin)->create(['name' => 'Switch de piso']);
    $this->travel(5)->hours();
    transfer($second, $this->destination, now()->subHours(3)->format(RecordAssetMovement::MOVED_AT_FORMAT), 'Traslado del switch');
    transfer($first, $this->destination, now()->subHours(2)->format(RecordAssetMovement::MOVED_AT_FORMAT), 'Primer traslado del router');
    transfer($first, $this->origin, now()->subHour()->format(RecordAssetMovement::MOVED_AT_FORMAT), 'Regreso del router');
    actingAsRole(SystemRole::Reportes);

    $this->get(route('reports.show', 'movements'))->assertOk()
        ->assertSeeInOrder(['Primer traslado del router', 'Regreso del router', 'Registro inicial', 'Traslado del switch'])
        ->assertSee('Traslado')->assertSee('LAB-101 · Laboratorio de redes')->assertSee('LAB-202 · Laboratorio de electrónica')
        ->assertSee('Admin Traslados');
});

test('movements can be filtered by asset, by type and by inclusive Costa Rica days', function () {
    $this->travelTo('2026-09-30 08:00');
    $router = Asset::factory()->inSpace($this->origin)->create(['name' => 'Router filtrado']);
    $switch = Asset::factory()->inSpace($this->origin)->create(['name' => 'Switch filtrado']);
    $this->travelTo('2026-10-03 12:00');
    transfer($router, $this->destination, '2026-10-01T00:10', 'Traslado a primera hora');
    transfer($switch, $this->destination, '2026-10-02T23:50', 'Traslado a última hora');
    actingAsRole(SystemRole::Reportes);

    Livewire::test('pages::reports.show', ['report' => movementsReport()])
        ->set('filters.asset.values', [(string) $router->id])
        ->assertSee('Traslado a primera hora')->assertDontSee('Traslado a última hora')->assertSee('Registro inicial')
        ->set('filters.type.values', ['transfer'])
        ->assertSee('Traslado a primera hora')->assertDontSee('Registro inicial')
        ->call('clearFilters')
        ->set('filters.moved_at', ['from' => '2026-10-01', 'to' => '2026-10-01'])
        ->assertSee('Traslado a primera hora')->assertDontSee('Traslado a última hora')
        ->set('filters.moved_at', ['from' => '2026-10-02', 'to' => '2026-10-02'])
        ->assertSee('Traslado a última hora')->assertDontSee('Traslado a primera hora');
});

test('each movements export keeps the selected type and writes the generation audit', function (string $format) {
    $asset = Asset::factory()->inSpace($this->origin)->create();
    $this->travel(2)->hours();
    transfer($asset, $this->destination, now()->subHour()->format(RecordAssetMovement::MOVED_AT_FORMAT), 'Traslado exportado');
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));

    $generation = app(ExportReport::class)->handle($author, 'movements', ReportFormat::from($format), ['type' => ['values' => ['transfer']]]);

    expect($generation->row_count)->toBe(1)
        ->and(AuditLog::where('action', AuditAction::Generated)->sole()->entity_id)->toBe($generation->id);
    if ($format === 'xlsx') {
        $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
        expect($rows)->toHaveCount(1)->and($rows[0]['Tipo de movimiento'])->toBe('Traslado')->and($rows[0]['Motivo'])->toBe('Traslado exportado');
    }
})->with(['pdf', 'xlsx']);

test('invalid movement filters produce Spanish feedback and no export', function (array $filters, string $message) {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    Livewire::test('pages::reports.show', ['report' => movementsReport()])
        ->set('filters', $filters)->assertSee($message)->assertDontSee(__('reports.show.preview'));
    Livewire::test('reports.export-button', ['report' => 'movements', 'filters' => $filters])->call('export', 'xlsx')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
})->with([
    'unknown key' => [['space' => ['values' => [1]]], 'El filtro space no está disponible.'],
    'unknown type' => [['type' => ['values' => ['loan']]], 'Selecciona una opción válida del filtro.'],
    'missing asset' => [['asset' => ['values' => [999999]]], 'Selecciona una opción válida del filtro.'],
    'inverted range' => [['moved_at' => ['from' => '2026-10-31', 'to' => '2026-10-01']], 'La fecha final no puede ser anterior a la inicial.'],
    'scalar values' => [['type' => ['values' => 'transfer']], 'La estructura del filtro no es válida.'],
]);
