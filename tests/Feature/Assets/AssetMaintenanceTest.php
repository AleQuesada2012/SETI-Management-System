<?php

// RF_10 · Registrar mantenimiento de activos, and RF_26 · Registrar mantenimiento preventivo: maintenance already done,
// recorded on the asset page with its kind, date, description and who did it, by the user who registers it.

use App\Actions\Assets\RegisterMaintenance;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create(['acquired_on' => '2024-03-01']);
});

/** @param  array<string, mixed>  $input */
function registerMaintenance(Asset $asset, array $input = [], ?User $actor = null): MaintenanceRecord
{
    return app(RegisterMaintenance::class)->handle($actor ?? test()->admin, $asset, $input + [
        'kind' => 'preventive',
        'performed_on' => today()->toDateString(),
        'performed_by' => 'Técnico de Soporte TI',
        'description' => 'Limpieza interna y revisión de ventiladores.',
    ]);
}

/**
 * @param  array<string, mixed>  $input
 * @return array<string, list<string>>
 */
function maintenanceErrors(Asset $asset, array $input): array
{
    try {
        registerMaintenance($asset, $input);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

test('an Administrador registers corrective maintenance done on an asset of their space, which shows on the asset', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->assertSee('data-test="register-maintenance-button"', false)
        ->assertSee(__('assets.maintenance.empty'))
        ->call('open')
        ->assertSet('kind', MaintenanceKind::Corrective->value)
        ->assertSet('performed_on', today()->toDateString())
        ->set('performed_on', '2026-10-01')
        ->set('performed_by', '  Taller Electrónico Alajuela ')
        ->set('description', 'Cambio de la fuente de poder.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.maintenance.done', ['asset' => $this->asset->code])])
        ->assertSeeHtml('data-test="maintenance-row"')
        ->assertSee('Cambio de la fuente de poder.');

    $record = MaintenanceRecord::query()->where('asset_id', $this->asset->id)->sole();

    expect($record->kind)->toBe(MaintenanceKind::Corrective)
        ->and($record->status)->toBe(MaintenanceStatus::Completed)
        ->and($record->started_on->toDateString())->toBe('2026-10-01')
        ->and($record->completed_on->toDateString())->toBe('2026-10-01')
        ->and($record->performed_by)->toBe('Taller Electrónico Alajuela')
        ->and($record->description)->toBe('Cambio de la fuente de poder.')
        ->and($record->registered_by)->toBe($this->admin->id)
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::InService);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSeeInOrder([__('assets.maintenance.title'), '01/10/2026', 'Correctivo', 'Cambio de la fuente de poder.', 'Taller Electrónico Alajuela', $this->admin->name]);
});

test('a Superusuario registers preventive maintenance of any asset', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    $record = registerMaintenance($other, ['kind' => 'preventive'], $superuser);

    expect($record->kind)->toBe(MaintenanceKind::Preventive)
        ->and($record->asset_id)->toBe($other->id)
        ->and($record->registered_by)->toBe($superuser->id);
});

test('registering maintenance is audited under the user who registered it', function () {
    $this->actingAs($this->admin);
    $record = registerMaintenance($this->asset);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'created', 'entity_type' => 'maintenance_record', 'entity_id' => $record->id, 'actor_id' => $this->admin->id,
    ]);
});

test('the maintenance shows in the asset history with who did the work and who registered it', function () {
    $this->actingAs($this->admin);
    registerMaintenance($this->asset, ['kind' => 'preventive', 'performed_on' => '2026-10-02']);

    $this->get(route('assets.history', $this->asset))
        ->assertOk()
        ->assertSeeHtml('data-type="maintenance"')
        ->assertSeeInOrder([
            __('assets.history.types.maintenance'), 'Preventivo', '02/10/2026',
            'Limpieza interna y revisión de ventiladores.',
            __('assets.history.performed_by', ['name' => 'Técnico de Soporte TI']),
            __('assets.history.by', ['user' => $this->admin->name]),
        ]);
});

test('an Administrador who does not manage the asset\'s space cannot register its maintenance', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    Livewire::test('assets.asset-maintenance', ['asset' => $other])
        ->assertDontSee('data-test="register-maintenance-button"', false)
        ->call('open')->assertForbidden();

    expect(fn () => registerMaintenance($other))->toThrow(AuthorizationException::class)
        ->and(MaintenanceRecord::query()->count())->toBe(0);
});

test('roles without maintenance.manage consult maintenance but cannot register it', function (SystemRole $role) {
    registerMaintenance($this->asset);
    $user = actingAsRole($role);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee('Limpieza interna y revisión de ventiladores.')
        ->assertDontSee('data-test="register-maintenance-button"', false);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->set('description', 'Otro')
        ->call('save')->assertForbidden();

    expect(fn () => registerMaintenance($this->asset, [], $user))->toThrow(AuthorizationException::class)
        ->and(MaintenanceRecord::query()->count())->toBe(1);
})->with([SystemRole::Visualizador, SystemRole::Reportes]);

test('the kind, date, responsible and description are required', function () {
    $errors = maintenanceErrors($this->asset, ['kind' => '', 'performed_on' => '', 'performed_by' => ' ', 'description' => '']);

    expect(array_keys($errors))->toEqualCanonicalizing(['kind', 'performed_on', 'performed_by', 'description'])
        ->and($errors['performed_by'])->toBe(['El campo responsable es obligatorio.'])
        ->and(MaintenanceRecord::query()->count())->toBe(0);
});

test('the kind must be corrective or preventive', function () {
    expect(maintenanceErrors($this->asset, ['kind' => 'scheduled']))->toHaveKey('kind');
});

test('the responsible fits in 150 characters', function () {
    expect(maintenanceErrors($this->asset, ['performed_by' => str_repeat('a', 151)]))->toHaveKey('performed_by');
});

test('the maintenance date must be a real past date, not before the acquisition', function (string $date, ?string $message) {
    $errors = maintenanceErrors($this->asset, ['performed_on' => $date]);

    expect($errors)->toHaveKey('performed_on');
    if ($message !== null) {
        expect($errors['performed_on'])->toBe([$message]);
    }
    expect(MaintenanceRecord::query()->count())->toBe(0);
})->with([
    'not a calendar day' => ['2026-02-30', null],
    'future' => [fn () => today()->addDay()->toDateString(), 'La fecha del mantenimiento no puede ser futura.'],
    'before acquisition' => ['2024-02-29', 'La fecha del mantenimiento no puede ser anterior a la fecha de adquisición del activo.'],
]);

test('an asset in maintenance takes maintenance, a lost or decommissioned one does not', function (AssetStatus $status, bool $allowed) {
    $this->actingAs($this->admin);
    $this->asset->forceFill(['status' => $status])->save();

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->{$allowed ? 'assertSee' : 'assertDontSee'}('data-test="register-maintenance-button"', false);

    expect(maintenanceErrors($this->asset, []))->toBe($allowed ? [] : ['form' => [__('assets.maintenance.errors.status')]])
        ->and(MaintenanceRecord::query()->count())->toBe($allowed ? 1 : 0);
})->with([
    'in maintenance' => [AssetStatus::InMaintenance, true],
    'lost' => [AssetStatus::Lost, false],
    'decommissioned' => [AssetStatus::Decommissioned, false],
]);

test('an asset lost after the form opened is refused when saving', function () {
    $stale = Asset::query()->findOrFail($this->asset->id);
    $this->asset->forceFill(['status' => AssetStatus::Lost])->save();

    expect(maintenanceErrors($stale, []))->toBe(['form' => [__('assets.maintenance.errors.status')]]);
});

test('the database refuses maintenance without a description', function () {
    // A savepoint, so the refused insert does not abort the test's transaction.
    expect(fn () => DB::transaction(fn () => MaintenanceRecord::query()->create([
        'asset_id' => $this->asset->id, 'kind' => 'preventive', 'status' => 'completed',
        'started_on' => today(), 'completed_on' => today(), 'description' => '  ',
    ])))->toThrow(QueryException::class, 'maintenance_records_description_check');
});
