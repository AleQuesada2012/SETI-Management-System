<?php

use App\Actions\Reports\ExportReport;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Space;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;

test('spaces preview and export use current data and the shared type status building filters', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $manager = userWithRole(SystemRole::Administrador);
    $wanted = Space::factory()->managedBy($manager)->create(['name' => 'Sala de Arquitectura', 'capacity' => 20, 'floor' => '2']);
    Space::factory()->office()->create(['name' => 'Oficina Excluida']);
    $filters = ['search' => 'arquitectura', 'space_type' => ['values' => [$wanted->space_type_id]],
        'space_status' => ['values' => [$wanted->space_status_id]], 'building' => ['values' => [$wanted->building_id]]];
    $this->get(route('reports.show', ['report' => 'spaces', 'filters' => $filters]))->assertOk()
        ->assertSee('Sala de Arquitectura')->assertSee('Piso 2')->assertSee($manager->name)->assertDontSee('Oficina Excluida');
    $wanted->update(['capacity' => 25]);
    $generation = app(ExportReport::class)->handle($author, 'spaces', ReportFormat::Xlsx, $filters);
    $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
    expect($rows)->toHaveCount(1)->and((int) $rows[0]['Capacidad'])->toBe(25)
        ->and($rows[0]['Encargados del espacio'])->toBe($manager->name);
    $pdf = app(ExportReport::class)->handle($author, 'spaces', ReportFormat::Pdf, $filters);
    expect(Storage::disk($pdf->file->disk)->get($pdf->file->path))->toStartWith('%PDF');
});

test('spaces reject invalid shared filters and empty matches remain informative', function () {
    $author = actingAsRole(SystemRole::Reportes);
    expect(fn () => app(ExportReport::class)->handle($author, 'spaces', ReportFormat::Pdf, ['building' => ['include_missing' => true]]))
        ->toThrow(ValidationException::class);
    $this->get(route('reports.show', ['report' => 'spaces', 'filters' => ['search' => 'No existe espacio']]))
        ->assertOk()->assertSee(__('reports.document.empty'));
});
