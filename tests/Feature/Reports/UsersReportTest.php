<?php

use App\Actions\Reports\ExportReport;
use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\Role;
use App\Models\User;
use App\Reports\Definitions\UsersReport;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

// RF_69 · current roles/account state, shared directory filters and secret-free exports.

test('the users report shows current roles and account states and combines both filters', function () {
    actingAsRole(SystemRole::Reportes);
    User::factory()->withRole(SystemRole::Reportes)->deactivated()->create(['name' => 'Carla Desactivada']);
    User::factory()->withRole(SystemRole::Reportes)->create(['name' => 'Diego Activo']);
    User::factory()->withRole(SystemRole::Administrador)->deactivated()->create(['name' => 'Eva Administradora']);
    $role = Role::findByName(SystemRole::Reportes->value)->id;

    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'users')->firstOrFail()])
        ->assertSee('Carla Desactivada')->assertSee('Diego Activo')->assertSee('Eva Administradora')
        ->assertSee('Estado de cuenta')->assertSee('Invitación pendiente')
        ->set('filters.role.values', [$role])
        ->set('filters.account_status.values', ['deactivated'])
        ->assertSee('Carla Desactivada')->assertDontSee('Diego Activo')->assertDontSee('Eva Administradora');
});

test('the report reads a user name, roles and status again after their data changes', function () {
    actingAsRole(SystemRole::Reportes);
    $user = User::factory()->withRole(SystemRole::Reservante)->create(['name' => 'Antes de actualizar']);
    $user->forceFill(['name' => 'Después de actualizar', 'deactivated_at' => now()])->save();
    $user->assignRole(SystemRole::Visualizador);

    $definition = app(UsersReport::class);
    $row = $definition->query([])->whereKey($user->id)->firstOrFail();
    expect($definition->cells($row))->toContain('Después de actualizar', 'Reservante, Visualizador', 'Desactivada');
    expect(array_keys($row->getAttributes()))->not->toContain('password', 'remember_token', 'two_factor_secret');
});

test('users XLSX exports only matching roles and statuses and records normalized filters', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    User::factory()->withRole(SystemRole::Reportes)->deactivated()->create(['name' => 'Carla Exportada']);
    $role = Role::findByName(SystemRole::Reportes->value)->id;
    $generation = app(ExportReport::class)->handle($author, 'users', ReportFormat::Xlsx, [
        'role' => ['values' => [$role, (string) $role]], 'account_status' => ['values' => ['deactivated']],
    ]);
    $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();
    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['Nombre' => 'Carla Exportada', 'Roles' => 'Reportes', 'Estado de cuenta' => 'Desactivada']);
    expect($generation->filters)->toBe(['role' => ['values' => [$role]], 'account_status' => ['values' => ['deactivated']]]);
    expect(AuditLog::where('action', AuditAction::Generated)->sole()->new_values['report'])->toBe('users');
});

test('an unknown role blocks users preview and export until cleared', function () {
    actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $filters = ['role' => ['values' => [999999]]];
    Livewire::test('pages::reports.show', ['report' => ReportType::where('code', 'users')->firstOrFail()])
        ->set('filters', $filters)->assertSee('El filtro «Rol» tiene una opción que no existe.')
        ->assertDontSee(__('reports.show.preview'))->call('clearFilters')->assertSee(__('reports.show.preview'));
    Livewire::test('reports.export-button', ['report' => 'users', 'filters' => $filters])
        ->call('export', 'pdf')->assertHasErrors();
    expect(ReportGeneration::query()->exists())->toBeFalse();
});

test('a user without report permission cannot open the users report', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.show', 'users'))->assertForbidden();
});
