<?php

// RF_11 · Registrar reparaciones de activos: corrective maintenance of a damaged asset, in progress (the asset is in
// maintenance) or finished (the asset takes a green or amber condition), linked to one of its damages; a repair in
// progress is finished or cancelled later (Agent-DB.md §8.4).

use App\Actions\Assets\CancelRepair;
use App\Actions\Assets\CompleteRepair;
use App\Actions\Assets\RegisterAssetDamage;
use App\Actions\Assets\RegisterAssetEvent;
use App\Actions\Assets\RegisterRepair;
use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\MaintenanceRecord;
use App\Models\Space;
use App\Models\User;
use Database\Factories\AssetEventFactory;
use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create(['acquired_on' => '2024-03-01']);
    $this->good = AssetCondition::query()->where('name', 'Buen estado')->sole();
    $this->fair = AssetCondition::query()->where('name', 'Regular')->sole();
    $this->damagedCondition = AssetCondition::query()->where('name', 'Dañado')->sole();
    $this->damage = app(RegisterAssetDamage::class)->handle($this->admin, $this->asset, [
        'occurred_on' => '2026-10-01',
        'description' => 'No enciende después de un apagón.',
        'condition_id' => $this->damagedCondition->id,
    ]);
    $this->asset->refresh();
});

/** @param  array<string, mixed>  $input */
function registerRepair(Asset $asset, array $input = [], ?User $actor = null): MaintenanceRecord
{
    return app(RegisterRepair::class)->handle($actor ?? test()->admin, $asset, $input + [
        'asset_event_id' => test()->damage->id,
        'performed_on' => today()->toDateString(),
        'performed_by' => 'Taller Electrónico Alajuela',
        'description' => 'Cambio de la fuente de poder.',
        'status' => 'in_progress',
    ]);
}

/**
 * @param  callable(): mixed  $action
 * @return array<string, list<string>>
 */
function repairErrors(callable $action): array
{
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

test('an Administrador registers a repair in progress of a damaged asset, which goes into maintenance', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->assertSee('data-test="register-repair-button"', false)
        ->call('openRepair')
        ->assertSet('damage_id', (string) $this->damage->id)
        ->assertSet('repair_status', MaintenanceStatus::InProgress->value)
        ->assertSet('performed_on', today()->toDateString())
        ->set('performed_on', '2026-10-02')
        ->set('performed_by', 'Taller Electrónico Alajuela')
        ->set('description', 'Cambio de la fuente de poder.')
        ->call('saveRepair')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.maintenance.repair.done.in_progress', ['asset' => $this->asset->code])])
        ->assertRedirect(route('assets.show', $this->asset));

    $repair = MaintenanceRecord::query()->where('asset_id', $this->asset->id)->sole();

    expect($repair->kind)->toBe(MaintenanceKind::Corrective)
        ->and($repair->status)->toBe(MaintenanceStatus::InProgress)
        ->and($repair->started_on->toDateString())->toBe('2026-10-02')
        ->and($repair->completed_on)->toBeNull()
        ->and($repair->asset_event_id)->toBe($this->damage->id)
        ->and($repair->registered_by)->toBe($this->admin->id);

    $asset = $this->asset->refresh();
    expect($asset->status)->toBe(AssetStatus::InMaintenance)
        ->and($asset->condition_id)->toBe($this->damagedCondition->id);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSeeInOrder(['02/10/2026', 'Correctivo', 'En curso', 'Cambio de la fuente de poder.', __('assets.maintenance.repair.repairs_damage', ['date' => '01/10/2026'])])
        ->assertSee('data-test="complete-repair-button"', false)
        ->assertDontSee('data-test="register-repair-button"', false);
});

test('a repair registered as finished gives the asset the condition chosen, and it stays in service', function () {
    $repair = registerRepair($this->asset, ['status' => 'completed', 'condition_id' => $this->fair->id]);

    expect($repair->status)->toBe(MaintenanceStatus::Completed)
        ->and($repair->completed_on->toDateString())->toBe(today()->toDateString())
        ->and($this->asset->refresh()->condition_id)->toBe($this->fair->id)
        ->and($this->asset->status)->toBe(AssetStatus::InService);
});

test('finishing a repair in progress puts the asset back in service with the condition chosen', function () {
    $this->actingAs($this->admin);
    $repair = registerRepair($this->asset, ['performed_on' => '2026-10-02']);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset->refresh()])
        ->call('openComplete', $repair->id)
        ->assertSet('completed_on', today()->toDateString())
        ->assertSet('condition_id', (string) $this->good->id)
        ->set('completed_on', '2026-10-05')
        ->call('complete')
        ->assertHasNoErrors()
        ->assertRedirect(route('assets.show', $this->asset));

    expect($repair->refresh()->status)->toBe(MaintenanceStatus::Completed)
        ->and($repair->started_on->toDateString())->toBe('2026-10-02')
        ->and($repair->completed_on->toDateString())->toBe('2026-10-05')
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::InService)
        ->and($this->asset->condition_id)->toBe($this->good->id);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'updated', 'entity_type' => 'maintenance_record', 'entity_id' => $repair->id, 'actor_id' => $this->admin->id,
    ]);
});

test('cancelling a repair in progress keeps it with its reason and puts the asset back in service, still damaged', function () {
    $this->actingAs($this->admin);
    $repair = registerRepair($this->asset);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset->refresh()])
        ->call('openCancel', $repair->id)
        ->set('cancellation_reason', 'Se registró en el activo equivocado.')
        ->call('cancelRecord')
        ->assertHasNoErrors()
        ->assertRedirect(route('assets.show', $this->asset));

    expect($repair->refresh()->status)->toBe(MaintenanceStatus::Cancelled)
        ->and($repair->cancellation_reason)->toBe('Se registró en el activo equivocado.')
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::InService)
        ->and($this->asset->condition_id)->toBe($this->damagedCondition->id);

    $this->get(route('assets.history', $this->asset))
        ->assertSeeInOrder(['Correctivo · Cancelado', 'Cambio de la fuente de poder.', __('assets.maintenance.repair.cancellation_reason', ['reason' => 'Se registró en el activo equivocado.'])]);
});

test('registering a repair is audited, and the history shows the damage it repairs', function () {
    $this->actingAs($this->admin);
    $repair = registerRepair($this->asset, ['status' => 'completed', 'condition_id' => $this->good->id]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'created', 'entity_type' => 'maintenance_record', 'entity_id' => $repair->id, 'actor_id' => $this->admin->id,
    ]);

    $this->get(route('assets.history', $this->asset))
        ->assertOk()
        ->assertSeeInOrder([
            'Correctivo', 'Cambio de la fuente de poder.',
            __('assets.history.performed_by', ['name' => 'Taller Electrónico Alajuela']),
            __('assets.maintenance.repair.repairs_damage', ['date' => '01/10/2026']),
        ]);
});

test('a repair needs a damaged asset in service or in maintenance without another repair in progress', function () {
    $this->actingAs($this->admin);
    $notAllowed = ['form' => [__('assets.maintenance.errors.repair_not_allowed')]];

    $healthy = Asset::factory()->inSpace($this->space)->create();
    Livewire::test('assets.asset-maintenance', ['asset' => $healthy])
        ->assertSee('data-test="register-maintenance-button"', false)
        ->assertDontSee('data-test="register-repair-button"', false);
    expect(repairErrors(fn () => registerRepair($healthy, ['asset_event_id' => null])))->toBe($notAllowed);

    registerRepair($this->asset);
    expect(repairErrors(fn () => registerRepair($this->asset->refresh())))->toBe($notAllowed);

    $lost = Asset::factory()->inSpace($this->space)->inCondition('Dañado')->create();
    $lost->forceFill(['status' => AssetStatus::Lost])->save();
    expect(repairErrors(fn () => registerRepair($lost, ['asset_event_id' => null])))->toBe($notAllowed)
        ->and(MaintenanceRecord::query()->count())->toBe(1);
});

test('the damage must be one of this asset\'s damages, and none is allowed', function () {
    $other = Asset::factory()->inSpace($this->space)->create();
    $otherDamage = AssetEventFactory::new()->kind(AssetEventKind::Damage)->create(['asset_id' => $other->id]);
    $loss = AssetEventFactory::new()->create(['asset_id' => $this->asset->id]);

    expect(repairErrors(fn () => registerRepair($this->asset, ['asset_event_id' => $otherDamage->id])))
        ->toBe(['asset_event_id' => [__('assets.maintenance.errors.damage')]])
        ->and(repairErrors(fn () => registerRepair($this->asset, ['asset_event_id' => $loss->id])))->toHaveKey('asset_event_id')
        ->and(registerRepair($this->asset, ['asset_event_id' => null])->asset_event_id)->toBeNull();
});

test('a finished repair needs a green or amber condition; one in progress needs none', function () {
    expect(repairErrors(fn () => registerRepair($this->asset, ['status' => 'completed'])))->toHaveKey('condition_id')
        ->and(repairErrors(fn () => registerRepair($this->asset, ['status' => 'completed', 'condition_id' => $this->damagedCondition->id])))
        ->toBe(['condition_id' => [__('assets.maintenance.errors.condition')]])
        ->and(repairErrors(fn () => registerRepair($this->asset, ['status' => 'scheduled'])))->toHaveKey('status')
        ->and(registerRepair($this->asset, ['condition_id' => $this->damagedCondition->id])->status)->toBe(MaintenanceStatus::InProgress);
});

test('a repair needs its date, responsible and description, with the maintenance date rules', function () {
    $errors = repairErrors(fn () => registerRepair($this->asset, ['performed_on' => '', 'performed_by' => '', 'description' => '']));

    expect(array_keys($errors))->toEqualCanonicalizing(['performed_on', 'performed_by', 'description'])
        ->and(repairErrors(fn () => registerRepair($this->asset, ['performed_on' => today()->addDay()->toDateString()])))
        ->toBe(['performed_on' => [__('assets.maintenance.errors.future')]]);
});

test('a repair ends on a real past day, not before it started', function (string $date, string $message) {
    $repair = registerRepair($this->asset, ['performed_on' => '2026-10-02']);

    expect(repairErrors(fn () => app(CompleteRepair::class)->handle($this->admin, $repair, ['completed_on' => $date, 'condition_id' => $this->good->id])))
        ->toBe(['completed_on' => [$message]])
        ->and($repair->refresh()->status)->toBe(MaintenanceStatus::InProgress);
})->with([
    'before the start' => ['2026-10-01', 'La fecha de finalización no puede ser anterior al inicio de la reparación.'],
    'future' => [fn () => today()->addDay()->toDateString(), 'La fecha del mantenimiento no puede ser futura.'],
]);

test('only a repair in progress can be finished or cancelled', function () {
    $repair = registerRepair($this->asset);
    $stale = MaintenanceRecord::query()->findOrFail($repair->id);
    app(CancelRepair::class)->handle($this->admin, $repair);

    $notInProgress = ['form' => [__('assets.maintenance.errors.not_in_progress')]];
    expect(repairErrors(fn () => app(CompleteRepair::class)->handle($this->admin, $stale, ['completed_on' => today()->toDateString(), 'condition_id' => $this->good->id])))
        ->toBe($notInProgress)
        ->and(repairErrors(fn () => app(CancelRepair::class)->handle($this->admin, $stale)))->toBe($notInProgress);

    $preventive = MaintenanceRecordFactory::new()->create(['asset_id' => $this->asset->id]);
    expect(repairErrors(fn () => app(CancelRepair::class)->handle($this->admin, $preventive)))->toBe($notInProgress);
});

test('an asset lost during a repair comes back in maintenance, and finishing the repair puts it in service', function () {
    $repair = registerRepair($this->asset);
    $event = ['occurred_on' => today()->toDateString(), 'description' => 'No aparece en el taller.'];

    app(RegisterAssetEvent::class)->handle($this->admin, $this->asset->refresh(), ['kind' => 'loss'] + $event);
    app(RegisterAssetEvent::class)->handle($this->admin, $this->asset->refresh(), ['kind' => 'recovery'] + $event);

    expect($this->asset->refresh()->status)->toBe(AssetStatus::InMaintenance)
        ->and($repair->refresh()->status)->toBe(MaintenanceStatus::InProgress);

    app(CompleteRepair::class)->handle($this->admin, $repair, ['completed_on' => today()->toDateString(), 'condition_id' => $this->good->id]);

    expect($this->asset->refresh()->status)->toBe(AssetStatus::InService);
});

test('the repair of an asset lost or decommissioned meanwhile can only be cancelled, and the asset keeps its status', function (string $kind, AssetStatus $status) {
    $repair = registerRepair($this->asset);
    app(RegisterAssetEvent::class)->handle($this->admin, $this->asset->refresh(), [
        'kind' => $kind, 'occurred_on' => today()->toDateString(), 'description' => 'Ya no está disponible.',
    ]);

    $this->actingAs($this->admin);
    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset->refresh()])
        ->assertSee('data-test="cancel-repair-button"', false)
        ->assertDontSee('data-test="complete-repair-button"', false);

    expect(repairErrors(fn () => app(CompleteRepair::class)->handle($this->admin, $repair, ['completed_on' => today()->toDateString(), 'condition_id' => $this->good->id])))
        ->toBe(['form' => [__('assets.maintenance.errors.not_in_progress')]]);

    app(CancelRepair::class)->handle($this->admin, $repair->refresh(), ['cancellation_reason' => 'El activo ya no está.']);

    expect($repair->refresh()->status)->toBe(MaintenanceStatus::Cancelled)
        ->and($this->asset->refresh()->status)->toBe($status);
})->with([
    'lost' => ['loss', AssetStatus::Lost],
    'decommissioned' => ['decommission', AssetStatus::Decommissioned],
]);

test('the lifecycle status only changes along its allowed transitions, and a decommission is terminal', function () {
    expect(AssetStatus::Decommissioned->transitions())->toBe([])
        ->and(AssetStatus::Lost->canTransitionTo(AssetStatus::InService))->toBeTrue()
        ->and(AssetStatus::InService->canTransitionTo(AssetStatus::InService))->toBeFalse();

    $this->asset->transitionTo(AssetStatus::Decommissioned);

    expect(fn () => $this->asset->transitionTo(AssetStatus::InService))->toThrow(LogicException::class)
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::Decommissioned);
});

test('an Administrador who does not manage the asset\'s space cannot register, finish or cancel its repairs', function () {
    $outsider = userWithRole(SystemRole::Administrador);
    $repair = registerRepair($this->asset);
    $this->actingAs($outsider);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset->refresh()])
        ->assertDontSee('data-test="complete-repair-button"', false)
        ->call('openRepair')->assertForbidden();
    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->call('openComplete', $repair->id)->assertForbidden();

    expect(fn () => registerRepair($this->asset, [], $outsider))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CompleteRepair::class)->handle($outsider, $repair, ['completed_on' => today()->toDateString(), 'condition_id' => $this->good->id]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CancelRepair::class)->handle($outsider, $repair))->toThrow(AuthorizationException::class)
        ->and($repair->refresh()->status)->toBe(MaintenanceStatus::InProgress);
});

test('roles without maintenance.manage see repairs but cannot register, finish or cancel them', function (SystemRole $role) {
    $repair = registerRepair($this->asset);
    $user = actingAsRole($role);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee('Cambio de la fuente de poder.')
        ->assertDontSee('data-test="register-repair-button"', false)
        ->assertDontSee('data-test="cancel-repair-button"', false);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset->refresh()])
        ->call('openCancel', $repair->id)->assertForbidden();

    expect(fn () => app(CancelRepair::class)->handle($user, $repair))->toThrow(AuthorizationException::class);
})->with([SystemRole::Visualizador, SystemRole::Reportes]);

test('a repair of another asset\'s record answers 404 from the card', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace($this->space)->inCondition('Dañado')->create();
    $repair = registerRepair($other, ['asset_event_id' => null]);

    Livewire::test('assets.asset-maintenance', ['asset' => $this->asset])
        ->call('openCancel', $repair->id)->assertNotFound();
});

test('the database refuses a repair of a damage of another asset', function () {
    $other = Asset::factory()->inSpace($this->space)->create();

    // A savepoint, so the refused insert does not abort the test's transaction.
    expect(fn () => DB::transaction(fn () => MaintenanceRecord::query()->create([
        'asset_id' => $other->id, 'kind' => 'corrective', 'status' => 'in_progress', 'started_on' => today(),
        'description' => 'Reparación', 'asset_event_id' => $this->damage->id,
    ])))->toThrow(QueryException::class, 'maintenance_records_asset_event_foreign');
});
