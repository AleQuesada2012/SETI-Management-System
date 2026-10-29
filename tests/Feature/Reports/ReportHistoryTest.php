<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// RF_84 · historical generation metadata and original stored downloads.

test('history identifies prior generations newest first with their date generator type and format', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    Asset::factory()->create();
    $this->travelTo(now()->setDate(2026, 10, 8)->setTime(10, 30));
    $first = app(ExportReport::class)->handle($author, 'assets_inventory', ReportFormat::Xlsx);
    $this->travelTo(now()->addDay()->setTime(14, 15));
    $second = app(ExportReport::class)->handle($author, 'spaces', ReportFormat::Pdf);
    actingAsRole(SystemRole::Superusuario);
    $this->get(route('reports.generations'))->assertOk()->assertSeeInOrder(['09/10/2026 14:15', '08/10/2026 10:30'])
        ->assertSee($author->name)->assertSee($first->reportType->name)->assertSee($second->reportType->name)
        ->assertSee('PDF')->assertSee('Excel (XLSX)')->assertSee(route('reports.generations.download', $first));
    expect(ReportGeneration::query()->count())->toBe(2)->and(AuditLog::where('action', AuditAction::Generated)->count())->toBe(2);
});

test('history downloads the original bytes after inventory changes and newer exports', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $asset = Asset::factory()->create(['name' => 'Nombre original']);
    $first = app(ExportReport::class)->handle($author, 'assets_inventory', ReportFormat::Xlsx);
    $original = Storage::disk($first->file->disk)->get($first->file->path);
    $asset->update(['name' => 'Nombre cambiado']);
    app(ExportReport::class)->handle($author, 'assets_inventory', ReportFormat::Xlsx);
    actingAsRole(SystemRole::Superusuario);
    $response = $this->get(route('reports.generations.download', $first))->assertOk()->assertDownload($first->file->original_name);
    expect($response->streamedContent())->toBe($original)->and(ReportGeneration::query()->count())->toBe(2);
});

test('removing a stored file keeps its generation in history with an unavailable label', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $generation = app(ExportReport::class)->handle($author, 'spaces', ReportFormat::Pdf);
    $generation->file->delete();
    actingAsRole(SystemRole::Superusuario);
    $this->get(route('reports.generations'))->assertOk()->assertSee($generation->reportType->name)
        ->assertSee('Archivo no disponible')->assertDontSee('Descargar original');
    $this->get(route('reports.generations.download', $generation))->assertNotFound();
    expect($generation->refresh()->file_id)->toBeNull();
});

test('empty history explains that no report has been generated', function () {
    actingAsRole(SystemRole::Superusuario);
    $this->get(route('reports.generations'))->assertOk()->assertSee('Todavía no se han generado reportes.');
});

test('ordinary report access does not grant access to every generation', function () {
    actingAsRole(SystemRole::Reportes);
    $this->get(route('reports.generations'))->assertForbidden();
    Livewire::test('pages::reports.generations')->assertForbidden();
});

test('older generations remain reachable on the next page with stable ordering', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $oldest = app(ExportReport::class)->handle($author, 'spaces', ReportFormat::Xlsx);
    for ($index = 0; $index < 25; $index++) {
        app(ExportReport::class)->handle($author, 'spaces', ReportFormat::Xlsx);
    }
    actingAsRole(SystemRole::Superusuario);
    $url = route('reports.generations.download', $oldest);
    $this->get(route('reports.generations'))->assertOk()->assertDontSee($url);
    $this->get(route('reports.generations', ['page' => 2]))->assertOk()->assertSee($url);
});
