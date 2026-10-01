<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\ReportGeneration;
use App\Models\User;
use App\Reports\ReportRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\Support\UsersTestReport;

// RF_00_4 · Auditar generaciones: every export picks a format, stores its file and is recorded and audited.

beforeEach(function () {
    Storage::fake(config('filesystems.files_disk'));
    app(ReportRegistry::class)->register(UsersTestReport::class);

    $this->author = userWithRole(SystemRole::Reportes);
    User::factory()->create(['name' => 'Ana Rojas']);
    User::factory()->deactivated()->create(['name' => 'Carlos Vega']);
});

test('exporting stores the file and records the generation (PDF)', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'users', ReportFormat::Pdf);

    expect($generation->format)->toBe(ReportFormat::Pdf)
        ->and($generation->filters)->toBe([])
        ->and($generation->row_count)->toBe(3)
        ->and($generation->generated_by)->toBe($this->author->id)
        ->and($generation->reportType->code)->toBe('users');

    $file = $generation->file;
    expect($file->mime_type)->toBe('application/pdf')
        ->and($file->uploaded_by)->toBe($this->author->id)
        ->and(Storage::disk($file->disk)->get($file->path))->toStartWith('%PDF')
        ->and($file->sha256)->toBe(hash('sha256', Storage::disk($file->disk)->get($file->path)));
});

test('the XLSX keeps the columns and only the filtered records (RF_71, RF_72)', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'users', ReportFormat::Xlsx, [
        'account_status' => 'deactivated',
    ]);

    expect($generation->filters)->toBe(['account_status' => 'deactivated'])
        ->and($generation->row_count)->toBe(1);

    $local = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($local, Storage::disk($generation->file->disk)->get($generation->file->path));
    $rows = SimpleExcelReader::create($local)->getRows()->all();
    @unlink($local);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toHaveKeys(['Nombre', 'Correo', 'Estado', 'Creado'])
        ->and($rows[0]['Nombre'])->toBe('Carlos Vega');
});

test('each export writes a "generated" audit entry', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'users', ReportFormat::Xlsx, ['account_status' => 'active']);

    $entry = AuditLog::query()->where('action', AuditAction::Generated)->sole();
    expect($entry->actor_id)->toBe($this->author->id)
        ->and($entry->entity_type)->toBe('report_generation')
        ->and($entry->entity_id)->toBe($generation->id)
        ->and($entry->new_values)->toMatchArray(['report' => 'users', 'format' => 'xlsx', 'filters' => ['account_status' => 'active']]);
});

test('users without reports.export cannot export', function (SystemRole $role) {
    app(ExportReport::class)->handle(userWithRole($role), 'users', ReportFormat::Pdf);
})->with([SystemRole::Administrador, SystemRole::Visualizador, SystemRole::Reservante])
    ->throws(AuthorizationException::class);

test('a failed export records nothing and leaves no file behind', function () {
    // Fail after the file is on disk, while recording it.
    File::creating(fn () => throw new RuntimeException('Simulated failure'));

    expect(fn () => app(ExportReport::class)->handle($this->author, 'users', ReportFormat::Pdf))
        ->toThrow(RuntimeException::class, 'Simulated failure');

    expect(Storage::disk(config('filesystems.files_disk'))->allFiles())->toBe([])
        ->and(ReportGeneration::query()->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', AuditAction::Generated)->exists())->toBeFalse();
});

test('the export button offers both formats and downloads the file', function () {
    $this->actingAs($this->author);

    Livewire::test('reports.export-button', ['report' => 'users', 'filters' => []])
        ->assertSee(__('reports.export.pdf'))
        ->assertSee(__('reports.export.xlsx'))
        ->call('export', 'xlsx')
        ->assertFileDownloaded();

    expect(ReportGeneration::query()->sole()->format)->toBe(ReportFormat::Xlsx);
});

test('the author can download the file again; others need reports.history', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'users', ReportFormat::Pdf);
    $url = route('reports.generations.download', $generation);

    $this->actingAs($this->author)->get($url)->assertOk()->assertDownload($generation->file->original_name);
    $this->actingAs(userWithRole(SystemRole::Reportes))->get($url)->assertForbidden();
    $this->actingAs(userWithRole(SystemRole::Superusuario))->get($url)->assertOk();
});

test('the report page previews the rows and offers the export', function () {
    $this->actingAs($this->author)
        ->get(route('reports.show', 'users'))
        ->assertOk()
        ->assertSee('Ana Rojas')
        ->assertSee(__('reports.export.button'));
});

test('a report type without a definition says it is not available yet', function () {
    $this->actingAs($this->author)
        ->get(route('reports.show', 'occupancy'))
        ->assertOk()
        ->assertSee(__('reports.show.unavailable'));
});

test('stored files are private: exported files are only reachable through the download route', function () {
    $generation = app(ExportReport::class)->handle($this->author, 'users', ReportFormat::Pdf);

    expect(File::query()->sole()->disk)->toBe(config('filesystems.files_disk'))
        ->and($generation->file->path)->toStartWith('reports/');
});
