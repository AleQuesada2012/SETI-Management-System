<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\User;
use App\Queries\Reports\AuditTrail;
use App\Reports\Definitions\AuditReport;
use App\Support\Audit\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_68 · original audit snapshots, actor/action filters and inclusive Costa Rica date boundaries.

test('the audit preview preserves recorded labels and values after a user is renamed', function () {
    actingAsRole(SystemRole::Reportes);
    $actor = User::factory()->create(['name' => 'Nombre original']);
    $entry = Audit::record(AuditAction::Updated, $actor, old: ['name' => 'Antes'], new: ['name' => 'Después'], actor: $actor);
    $actor->update(['name' => 'Nombre actual']);

    $this->get(route('reports.show', 'audit'))->assertSee('Nombre original')->assertSee('Valores anteriores')->assertSee('Después');
    $row = AuditTrail::query()->whereKey($entry->id)->firstOrFail();
    expect($row->actor_label)->toBe($entry->actor_label)
        ->and($row->entity_label)->toBe($entry->entity_label)
        ->and(json_decode($row->old_values, true))->toBe(['name' => 'Antes'])
        ->and(json_decode($row->new_values, true))->toBe(['name' => 'Después']);
});

test('actor and operation selections combine and date bounds include entire Costa Rica days', function () {
    $actor = userWithRole(SystemRole::Reportes);
    $other = User::factory()->create();
    $entries = [];
    foreach (['2026-10-14T05:59:59Z', '2026-10-14T06:00:00Z', '2026-10-15T05:59:59Z', '2026-10-15T06:00:00Z'] as $time) {
        $entries[] = AuditLog::query()->forceCreate([
            'occurred_at' => CarbonImmutable::parse($time)->timezone('America/Costa_Rica'), 'actor_id' => $actor->id, 'actor_label' => $actor->name,
            'action' => 'updated', 'entity_type' => 'user', 'entity_id' => $actor->id,
            'entity_label' => $actor->name, 'channel' => 'cli', 'request_id' => Str::uuid(),
        ]);
    }
    Audit::record(AuditAction::Updated, $other, actor: $other);
    Audit::record(AuditAction::Created, $actor, actor: $actor);
    $filters = AuditTrail::validate([
        'actor' => ['values' => [$actor->id, (string) $actor->id]],
        'action' => ['values' => ['updated']],
        'occurred_at' => ['from' => '2026-10-14', 'to' => '2026-10-14'],
    ]);

    expect(AuditTrail::query($filters)->pluck('id')->all())->toBe([$entries[2]->id, $entries[1]->id]);
    expect(app(AuditReport::class)->cells(AuditTrail::query($filters)->whereKey($entries[1]->id)->firstOrFail()))
        ->toContain('14/10/2026 00:00:00');
});

test('each audit date bound can be used alone and empty restrictions normalize to all records', function () {
    expect(AuditTrail::validate(['actor' => ['values' => []], 'action' => null, 'occurred_at' => ['from' => '', 'to' => null]]))->toBe([]);
    $entry = AuditLog::query()->forceCreate([
        'occurred_at' => CarbonImmutable::parse('2026-10-14T00:00:00-06:00'), 'action' => 'updated', 'channel' => 'cli', 'request_id' => Str::uuid(),
    ]);
    expect(AuditTrail::query(['occurred_at' => ['from' => '2026-10-14']])->pluck('id')->all())->toContain($entry->id)
        ->and(AuditTrail::query(['occurred_at' => ['to' => '2026-10-13']])->pluck('id')->all())->not->toContain($entry->id);
});

test('invalid audit filters block preview and generation with Spanish feedback', function (array $filters, string $message) {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));

    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'audit')->firstOrFail()])
        ->set('filters', $filters)->assertSee($message)->assertDontSee(__('reports.show.preview'))
        ->call('clearFilters')->assertSee(__('reports.show.preview'));
    Livewire::test('reports.export-button', ['report' => 'audit', 'filters' => $filters])
        ->call('export', 'xlsx')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
})->with([
    'unknown key' => [['search' => 'x'], 'El filtro search no está disponible.'],
    'malformed actor' => [['actor' => 'x'], 'La estructura del filtro no es válida.'],
    'empty scalar criterion' => [['occurred_at' => ''], 'La estructura del filtro no es válida.'],
    'whitespace scalar criterion' => [['actor' => ' '], 'La estructura del filtro no es válida.'],
    'empty scalar selections' => [['action' => ['values' => '']], 'La estructura del filtro no es válida.'],
    'null selection' => [['actor' => ['values' => [null]]], 'Selecciona una opción válida del filtro.'],
    'unknown actor' => [['actor' => ['values' => [999999]]], 'Selecciona una opción válida del filtro.'],
    'unknown member' => [['actor' => ['other' => 1]], 'La estructura del filtro no es válida.'],
    'unknown action' => [['action' => ['values' => ['destroy']]], 'Selecciona una opción válida del filtro.'],
    'invalid date' => [['occurred_at' => ['from' => '2026-02-30']], 'Introduce una fecha válida con formato AAAA-MM-DD.'],
    'inverted dates' => [['occurred_at' => ['from' => '2026-10-14', 'to' => '2026-10-13']], 'La fecha final no puede ser anterior a la inicial.'],
]);

test('audit XLSX exports original snapshots and records its generation without rewriting history', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $entry = Audit::record(AuditAction::RoleAssigned, $author, new: ['role' => 'Reportes'], actor: $author);
    $before = $entry->refresh()->getRawOriginal();

    $generation = app(ExportReport::class)->handle($author, 'audit', ReportFormat::Xlsx, ['action' => ['values' => ['role_assigned']]]);
    $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Usuario registrado'])->toBe($entry->actor_label)
        ->and(json_decode($rows[0]['Valores nuevos'], true))->toBe(['role' => 'Reportes']);
    expect($entry->refresh()->getRawOriginal())->toBe($before);
    expect(AuditLog::where('action', AuditAction::Generated)->sole()->entity_id)->toBe($generation->id);
});

test('reports.view and reports.export alone do not grant audit preview or export', function () {
    $user = actingAsRole(SystemRole::Visualizador);
    $user->givePermissionTo(PermissionName::ReportsView, PermissionName::ReportsExport);
    Storage::fake(config('filesystems.files_disk'));

    $this->get(route('reports.show', 'audit'))->assertForbidden();
    Livewire::test('reports.export-button', ['report' => 'audit'])->call('export', 'pdf')->assertForbidden();
    expect(ReportGeneration::query()->exists())->toBeFalse();
});
