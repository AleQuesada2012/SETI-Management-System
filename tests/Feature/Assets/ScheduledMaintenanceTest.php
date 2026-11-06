<?php

// RF_27 · Programar mantenimiento de activos: maintenance planned for a day after today, changed, marked as done or
// cancelled from the asset's card, and every scheduled maintenance listed on the "Mantenimientos programados" page.

use App\Actions\Assets\CancelScheduledMaintenance;
use App\Actions\Assets\CompleteScheduledMaintenance;
use App\Actions\Assets\ScheduleMaintenance;
use App\Actions\Assets\UpdateScheduledMaintenance;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\MaintenanceRecord;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-11-02 08:00');
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110']);
    $this->asset = Asset::factory()->inSpace($this->space)->create(['acquired_on' => '2024-03-01']);
});

afterEach(fn () => Carbon::setTestNow());

/** @param  array<string, mixed>  $input */
function scheduleMaintenance(Asset $asset, array $input = [], ?User $actor = null): MaintenanceRecord
{
    return app(ScheduleMaintenance::class)->handle($actor ?? test()->admin, $asset, $input + [
        'kind' => 'preventive',
        'scheduled_for' => '2026-11-10',
        'description' => 'Revisión semestral de ventiladores.',
    ]);
}

/**
 * @param  callable(): mixed  $action
 * @return array<string, list<string>>
 */
function scheduledErrors(callable $action): array
{
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

test('an Administrador schedules preventive maintenance of an asset of their space, which shows on its card', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->assertSee('data-test="schedule-maintenance-button"', false)
        ->call('openSchedule')
        ->assertSet('kind', MaintenanceKind::Preventive->value)
        ->assertSet('scheduled_for', '2026-11-03')
        ->set('scheduled_for', '2026-11-10')
        ->set('description', 'Revisión semestral de ventiladores.')
        ->call('saveSchedule')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.maintenance.scheduled.done.created', ['asset' => $this->asset->code, 'date' => '10/11/2026'])])
        ->assertSeeHtml('data-test="complete-scheduled-button"')
        ->assertSeeHtmlInOrder(['10/11/2026', 'Preventivo', 'Programado', 'Revisión semestral de ventiladores.']);

    $record = MaintenanceRecord::query()->where('asset_id', $this->asset->id)->sole();

    expect($record->kind)->toBe(MaintenanceKind::Preventive)
        ->and($record->status)->toBe(MaintenanceStatus::Scheduled)
        ->and($record->scheduled_for->toDateString())->toBe('2026-11-10')
        ->and($record->started_on)->toBeNull()
        ->and($record->registered_by)->toBe($this->admin->id)
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::InService);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'created', 'entity_type' => 'maintenance_record', 'entity_id' => $record->id, 'actor_id' => $this->admin->id,
    ]);
});

test('a Superusuario schedules corrective maintenance of any asset', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    expect(scheduleMaintenance($other, ['kind' => 'corrective'], $superuser)->kind)->toBe(MaintenanceKind::Corrective);
});

test('the planned day must be a real day after today', function (string $date, ?string $message) {
    $errors = scheduledErrors(fn () => scheduleMaintenance($this->asset, ['scheduled_for' => $date]));

    expect($errors)->toHaveKey('scheduled_for');
    if ($message !== null) {
        expect($errors['scheduled_for'])->toBe([$message]);
    }
    expect(MaintenanceRecord::query()->count())->toBe(0);
})->with([
    'today' => ['2026-11-02', 'La fecha programada debe ser posterior a hoy.'],
    'past' => ['2026-10-30', 'La fecha programada debe ser posterior a hoy.'],
    'not a calendar day' => ['2026-02-30', null],
]);

test('scheduling needs the kind, the day and a description', function () {
    $errors = scheduledErrors(fn () => scheduleMaintenance($this->asset, ['kind' => '', 'scheduled_for' => '', 'description' => ' ']));

    expect(array_keys($errors))->toEqualCanonicalizing(['kind', 'scheduled_for', 'description']);
});

test('changing a scheduled maintenance updates its day, kind and description, and audits the old values', function () {
    $this->actingAs($this->admin);
    $record = scheduleMaintenance($this->asset);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->call('openSchedule', $record->id)
        ->assertSet('scheduled_for', '2026-11-10')
        ->assertSet('description', 'Revisión semestral de ventiladores.')
        ->set('scheduled_for', '2026-11-20')
        ->set('kind', 'corrective')
        ->set('description', 'Cambio del disco duro.')
        ->call('saveSchedule')
        ->assertHasNoErrors()
        ->assertSee('20/11/2026');

    $record->refresh();
    expect($record->scheduled_for->toDateString())->toBe('2026-11-20')
        ->and($record->kind)->toBe(MaintenanceKind::Corrective)
        ->and($record->description)->toBe('Cambio del disco duro.')
        ->and($record->status)->toBe(MaintenanceStatus::Scheduled);

    $log = AuditLog::query()->where(['action' => 'updated', 'entity_type' => 'maintenance_record', 'entity_id' => $record->id])->sole();
    expect($log->old_values['scheduled_for'])->toStartWith('2026-11-10')
        ->and($log->new_values['scheduled_for'])->toStartWith('2026-11-20')
        ->and($log->actor_id)->toBe($this->admin->id);

    expect(scheduledErrors(fn () => app(UpdateScheduledMaintenance::class)->handle($this->admin, $record, [
        'kind' => 'preventive', 'scheduled_for' => '2026-11-02', 'description' => 'Hoy',
    ])))->toBe(['scheduled_for' => ['La fecha programada debe ser posterior a hoy.']]);
});

test('marking a scheduled maintenance as done completes it with who did it, keeping the planned day', function () {
    $this->actingAs($this->admin);
    $record = scheduleMaintenance($this->asset);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->call('openCompleteScheduled', $record->id)
        ->assertSet('performed_on', '2026-11-02')
        ->assertSet('description', 'Revisión semestral de ventiladores.')
        ->set('performed_by', 'Técnico de Soporte TI')
        ->call('completeScheduled')
        ->assertHasNoErrors()
        ->assertDontSeeHtml('data-test="complete-scheduled-button"');

    $record->refresh();
    expect($record->status)->toBe(MaintenanceStatus::Completed)
        ->and($record->started_on->toDateString())->toBe('2026-11-02')
        ->and($record->completed_on->toDateString())->toBe('2026-11-02')
        ->and($record->performed_by)->toBe('Técnico de Soporte TI')
        ->and($record->scheduled_for->toDateString())->toBe('2026-11-10')
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::InService);

    expect(scheduledErrors(fn () => app(CompleteScheduledMaintenance::class)->handle($this->admin, scheduleMaintenance($this->asset), [
        'performed_on' => '2026-11-03', 'performed_by' => 'Técnico', 'description' => 'Hecho',
    ])))->toBe(['performed_on' => [__('assets.maintenance.errors.future')]]);
});

test('cancelling a scheduled maintenance keeps it with its reason', function () {
    $this->actingAs($this->admin);
    $record = scheduleMaintenance($this->asset);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->call('openCancel', $record->id)
        ->assertSee(__('assets.maintenance.scheduled.cancel_title', ['asset' => $this->asset->code]))
        ->set('cancellation_reason', 'El técnico no está disponible.')
        ->call('cancelRecord')
        ->assertHasNoErrors()
        ->assertSee('Cancelado')
        ->assertSee(__('assets.maintenance.repair.cancellation_reason', ['reason' => 'El técnico no está disponible.']));

    expect($record->refresh()->status)->toBe(MaintenanceStatus::Cancelled)
        ->and($record->cancellation_reason)->toBe('El técnico no está disponible.');
});

test('the maintenance of an asset lost meanwhile can only be cancelled', function () {
    $this->actingAs($this->admin);
    $record = scheduleMaintenance($this->asset);
    $this->asset->forceFill(['status' => AssetStatus::Lost])->save();
    $statusError = ['form' => [__('assets.maintenance.errors.status')]];

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->assertDontSeeHtml('data-test="schedule-maintenance-button"')
        ->assertDontSeeHtml('data-test="edit-scheduled-button"')
        ->assertSeeHtml('data-test="cancel-scheduled-button"');

    expect(scheduledErrors(fn () => scheduleMaintenance($this->asset)))->toBe($statusError)
        ->and(scheduledErrors(fn () => app(UpdateScheduledMaintenance::class)->handle($this->admin, $record, [
            'kind' => 'preventive', 'scheduled_for' => '2026-11-20', 'description' => 'Otra fecha',
        ])))->toBe($statusError)
        ->and(app(CancelScheduledMaintenance::class)->handle($this->admin, $record)->status)->toBe(MaintenanceStatus::Cancelled);
});

test('only a scheduled maintenance can be changed, marked as done or cancelled', function () {
    $record = scheduleMaintenance($this->asset);
    $stale = MaintenanceRecord::query()->findOrFail($record->id);
    app(CancelScheduledMaintenance::class)->handle($this->admin, $record);
    $notScheduled = ['form' => [__('assets.maintenance.errors.not_scheduled')]];

    expect(scheduledErrors(fn () => app(UpdateScheduledMaintenance::class)->handle($this->admin, $stale, [
        'kind' => 'preventive', 'scheduled_for' => '2026-11-20', 'description' => 'Otra fecha',
    ])))->toBe($notScheduled)
        ->and(scheduledErrors(fn () => app(CompleteScheduledMaintenance::class)->handle($this->admin, $stale, [
            'performed_on' => '2026-11-02', 'performed_by' => 'Técnico', 'description' => 'Hecho',
        ])))->toBe($notScheduled)
        ->and(scheduledErrors(fn () => app(CancelScheduledMaintenance::class)->handle($this->admin, $stale)))->toBe($notScheduled);
});

test('an Administrador who does not manage the asset\'s space cannot schedule, change or cancel its maintenance', function () {
    $outsider = userWithRole(SystemRole::Administrador);
    $record = scheduleMaintenance($this->asset);
    $this->actingAs($outsider);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->assertDontSeeHtml('data-test="schedule-maintenance-button"')
        ->assertDontSeeHtml('data-test="cancel-scheduled-button"')
        ->call('openSchedule')->assertForbidden();

    expect(fn () => scheduleMaintenance($this->asset, [], $outsider))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CancelScheduledMaintenance::class)->handle($outsider, $record))->toThrow(AuthorizationException::class)
        ->and($record->refresh()->status)->toBe(MaintenanceStatus::Scheduled);
});

test('roles without maintenance.manage consult scheduled maintenance but cannot manage it', function (SystemRole $role) {
    $record = scheduleMaintenance($this->asset);
    $user = actingAsRole($role);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee('Revisión semestral de ventiladores.')
        ->assertDontSee('data-test="schedule-maintenance-button"', false)
        ->assertDontSee('data-test="cancel-scheduled-button"', false);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->call('openSchedule', $record->id)->assertForbidden();

    expect(fn () => app(UpdateScheduledMaintenance::class)->handle($user, $record, [
        'kind' => 'preventive', 'scheduled_for' => '2026-11-20', 'description' => 'Otra fecha',
    ]))->toThrow(AuthorizationException::class);
})->with([SystemRole::Visualizador, SystemRole::Reportes]);

test('the scheduled maintenance page lists every scheduled maintenance, soonest first, and filters by when it is due', function () {
    $other = Asset::factory()->inSpace(Space::factory()->create(['code' => 'LAB-322', 'name' => 'Laboratorio 322']))->create();
    scheduleMaintenance($this->asset, ['scheduled_for' => '2026-11-06', 'description' => 'Revisión esta semana']);
    scheduleMaintenance($this->asset, ['scheduled_for' => '2026-11-25', 'description' => 'Revisión en tres semanas']);
    $overdue = scheduleMaintenance($other, ['scheduled_for' => '2026-11-05', 'description' => 'Calibración atrasada'], userWithRole(SystemRole::Superusuario));
    $overdue->forceFill(['scheduled_for' => '2026-10-28'])->save();
    $done = scheduleMaintenance($this->asset, ['description' => 'Ya realizado']);
    $done->forceFill(['status' => MaintenanceStatus::Completed, 'started_on' => '2026-11-01', 'completed_on' => '2026-11-01'])->save();
    $deleted = Asset::factory()->inSpace($this->space)->create();
    scheduleMaintenance($deleted, ['description' => 'De un activo eliminado']);
    $deleted->delete();

    actingAsRole(SystemRole::Visualizador);

    $this->get(route('maintenance.index'))
        ->assertOk()
        ->assertSeeInOrder([
            '28/10/2026', __('assets.maintenance.scheduled.overdue'), $other->code, 'LAB-322 · Laboratorio 322', 'Calibración atrasada',
            '06/11/2026', $this->asset->code, 'LAB-110 · Laboratorio 110', 'Revisión esta semana',
            '25/11/2026', 'Revisión en tres semanas',
        ])
        ->assertDontSee('Ya realizado')
        ->assertDontSee('De un activo eliminado');

    Livewire::test('pages::assets.maintenance')
        ->set('when', 'overdue')
        ->assertSee('Calibración atrasada')->assertDontSee('Revisión esta semana')
        ->set('when', 'week')
        ->assertSee('Revisión esta semana')->assertDontSee('Calibración atrasada')->assertDontSee('Revisión en tres semanas')
        ->set('when', 'month')
        ->assertSee('Revisión en tres semanas');
});

test('the scheduled maintenance page says when there is nothing scheduled', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('maintenance.index'))->assertOk()->assertSee(__('assets.scheduled.empty'));
    Livewire::test('pages::assets.maintenance')->set('when', 'overdue')->assertSee(__('assets.scheduled.empty_filtered'));
});

test('only roles that consult maintenance open the page and see it in the menu', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('assets.index'))->assertSee(route('maintenance.index'), false);

    actingAsRole(SystemRole::Reservante);
    $this->get(route('maintenance.index'))->assertForbidden();
    $this->get(route('reservations.index'))->assertOk()->assertDontSee(route('maintenance.index'), false);
});
