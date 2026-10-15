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
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_78 · current responsible users and explicit missing assignments.

test('custodians can be filtered including inactive users and unassigned assets', function () {
    actingAsRole(SystemRole::Reportes);
    $custodian = User::factory()->deactivated()->create(['name' => 'Responsable histórico']);
    Asset::factory()->create(['name' => 'Activo asignado', 'responsible_user_id' => $custodian->id]);
    Asset::factory()->create(['name' => 'Activo pendiente']);
    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'custodians')->firstOrFail()])
        ->assertSee('Activo asignado')->assertSee('Responsable histórico')->assertSee('Sin responsable')
        ->set('filters.responsible_user.values', [$custodian->id])->assertSee('Activo asignado')->assertDontSee('Activo pendiente')
        ->set('filters.responsible_user.include_missing', true)->assertSee('Activo pendiente')->assertSee('Activo asignado')
        ->set('filters.responsible_user.values', [])->assertSee('Activo pendiente')->assertDontSee('Activo asignado')
        ->call('clearFilters')->assertSee('Activo asignado');
});

test('custodian exports reflect a changed assignment and record normalized filters and audit', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $custodian = User::factory()->create();
    $asset = Asset::factory()->create(['name' => 'Asignación actual', 'responsible_user_id' => $custodian->id]);
    $asset->update(['responsible_user_id' => null]);
    $generation = app(ExportReport::class)->handle($author, 'custodians', ReportFormat::Xlsx, ['responsible_user' => ['include_missing' => true]]);
    $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
    expect($rows)->toHaveCount(1)->and($rows[0]['Responsable del activo'])->toBe('Sin responsable');
    expect($generation->filters)->toBe(['responsible_user' => ['include_missing' => true]])
        ->and(AuditLog::where('action', AuditAction::Generated)->sole()->entity_id)->toBe($generation->id);
});

test('unknown custodian filters block preview and export without a generation', function () {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $filters = ['responsible_user' => ['values' => [999999]]];
    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'custodians')->firstOrFail()])
        ->set('filters', $filters)->assertDontSee(__('reports.show.preview'))->assertSee(__('reports.filters.invalid'));
    Livewire::test('reports.export-button', ['report' => 'custodians', 'filters' => $filters])->call('export', 'pdf')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
});

test('custodians requires report permission', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.show', 'custodians'))->assertForbidden();
});
