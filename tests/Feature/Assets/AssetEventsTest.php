<?php

// RF_29 · Dar de baja un activo, with RF_22 · Registrar fecha de baja de activos, and RF_28 · Registrar pérdidas de
// activos, with its recovery: append-only asset events and their side effect on the lifecycle status (Agent-DB.md §8.4).

use App\Actions\Assets\AddAssetComponent;
use App\Actions\Assets\AttachAssetFiles;
use App\Actions\Assets\RecordAssetMovement;
use App\Actions\Assets\RegisterAssetEvent;
use App\Actions\Assets\RemoveAssetFile;
use App\Actions\Assets\UpdateAsset;
use App\Enums\AssetEventKind;
use App\Enums\AssetFileKind;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\AuditLog;
use App\Models\Reporting\DashboardKpis;
use App\Models\Reporting\SpaceRow;
use App\Models\Space;
use App\Queries\Assets\AssetInventory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create(['acquired_on' => '2024-03-01']);
});

/** @param  array<string, mixed>  $input */
function registerEvent(Asset $asset, array $input, $actor = null): AssetEvent
{
    return app(RegisterAssetEvent::class)->handle($actor ?? test()->admin, $asset, $input + [
        'occurred_on' => today()->toDateString(),
        'description' => 'Equipo obsoleto, sin repuestos.',
    ]);
}

/** @return array<string, list<string>> */
function eventErrors(Asset $asset, array $input): array
{
    try {
        registerEvent($asset, $input);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

test('an Administrador decommissions an asset of their space with its date, which shows on the asset', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-events', ['asset' => $this->asset])
        ->assertSee('data-test="register-event-button"', false)
        ->call('open')
        ->assertSet('kind', AssetEventKind::Loss->value)
        ->assertSet('occurred_on', today()->toDateString())
        ->set('kind', AssetEventKind::Decommission->value)
        ->set('occurred_on', '2026-10-01')
        ->set('description', 'Equipo obsoleto, sin repuestos.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.events.done.decommission', ['asset' => $this->asset->code])])
        ->assertRedirect(route('assets.show', $this->asset));

    $event = AssetEvent::query()->where('asset_id', $this->asset->id)->sole();

    expect($event->kind)->toBe(AssetEventKind::Decommission)
        ->and($event->occurred_on->toDateString())->toBe('2026-10-01')
        ->and($event->description)->toBe('Equipo obsoleto, sin repuestos.')
        ->and($event->registered_by)->toBe($this->admin->id)
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::Decommissioned);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSeeInOrder([__('assets.show.status_since.decommissioned'), '01/10/2026'])
        ->assertSee('data-test="decommissioned-notice"', false)
        ->assertSee('Equipo obsoleto, sin repuestos.');
});

test('a decommission and its status change are audited together', function () {
    $this->actingAs($this->admin);
    $event = registerEvent($this->asset, ['kind' => 'decommission']);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'created', 'entity_type' => 'asset_event', 'entity_id' => $event->id, 'actor_id' => $this->admin->id,
    ]);
    $log = AuditLog::query()->where(['action' => 'updated', 'entity_type' => 'asset', 'entity_id' => $this->asset->id])->sole();

    expect($log->old_values)->toBe(['status' => 'in_service'])
        ->and($log->new_values)->toBe(['status' => 'decommissioned'])
        ->and($log->actor_id)->toBe($this->admin->id);
});

test('the decommission date must be a real past date, not before the acquisition', function (string $date, ?string $message) {
    $errors = eventErrors($this->asset, ['kind' => 'decommission', 'occurred_on' => $date]);

    expect($errors)->toHaveKey('occurred_on');
    if ($message !== null) {
        expect($errors['occurred_on'])->toBe([$message]);
    }
    expect($this->asset->refresh()->status)->toBe(AssetStatus::InService)
        ->and(AssetEvent::query()->count())->toBe(0);
})->with([
    'not a calendar day' => ['2026-02-30', null],
    'not a date' => ['ayer', null],
    'future' => [fn () => today()->addDay()->toDateString(), 'La fecha del evento no puede ser futura.'],
    'before acquisition' => ['2024-02-29', 'La fecha del evento no puede ser anterior a la fecha de adquisición del activo.'],
]);

test('an event needs a description', function () {
    expect(eventErrors($this->asset, ['kind' => 'loss', 'description' => '  ']))->toHaveKey('description');
});

test('a decommissioned asset cannot be decommissioned again, and the database refuses a second decommission', function () {
    $stale = Asset::query()->findOrFail($this->asset->id);
    registerEvent($this->asset, ['kind' => 'decommission']);

    expect(eventErrors($this->asset->refresh(), ['kind' => 'decommission']))->toHaveKey('kind')
        ->and(eventErrors($stale, ['kind' => 'decommission']))->toBe(['kind' => [__('assets.events.errors.kind')]]);

    // A savepoint, so the refused insert does not abort the test's transaction.
    expect(fn () => DB::transaction(fn () => AssetEvent::query()->create([
        'asset_id' => $this->asset->id, 'kind' => 'decommission', 'occurred_on' => today(), 'description' => 'Otra baja',
    ])))->toThrow(QueryException::class, 'asset_events_one_decommission');
    expect(AssetEvent::query()->where('kind', 'decommission')->count())->toBe(1);
});

test('a decommissioned asset is no longer available to transfer or assign, but may still exit its space', function () {
    registerEvent($this->asset, ['kind' => 'decommission']);
    $asset = $this->asset->refresh();
    $other = Space::factory()->managedBy($this->admin)->create();

    expect(RecordAssetMovement::allowedTypes($asset))->toBe([MovementType::Exit])
        ->and($asset->status->isAvailable())->toBeFalse()
        ->and(SpaceRow::query()->findOrFail($this->space->id)->asset_count)->toBe(0)
        ->and(fn () => app(RecordAssetMovement::class)->handle($this->admin, $asset, ['type' => 'transfer', 'to_space_id' => $other->id]))
        ->toThrow(ValidationException::class);

    app(RecordAssetMovement::class)->handle($this->admin, $asset, ['type' => 'exit', 'reason' => 'Desecho']);

    expect($asset->refresh()->current_space_id)->toBeNull();
});

test('a decommissioned asset keeps its history and stays listed with its status', function () {
    registerEvent($this->asset, ['kind' => 'decommission']);

    expect($this->asset->movements()->count())->toBe(1)
        ->and(AssetInventory::query(['lifecycle_status' => ['values' => ['decommissioned']]])->pluck('code')->all())
        ->toBe([$this->asset->code]);

    $this->actingAs($this->admin)->get(route('assets.index'))->assertSee($this->asset->code)->assertSee(AssetStatus::Decommissioned->label());
});

test('a decommissioned asset is read-only', function () {
    Storage::fake(config('filesystems.files_disk'));
    $photo = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Photo, [UploadedFile::fake()->image('frente.jpg')])->sole();
    registerEvent($this->asset, ['kind' => 'decommission']);
    $asset = $this->asset->refresh();
    $this->actingAs($this->admin);

    $this->get(route('assets.show', $asset))
        ->assertDontSee('data-test="edit-asset-button"', false)
        ->assertDontSee('data-test="register-event-button"', false)
        ->assertDontSee('data-test="asset-photos-input"', false)
        ->assertDontSee('data-test="add-component-button"', false)
        ->assertSee('data-test="delete-asset-button"', false);
    $this->get(route('assets.edit', $asset))->assertOk()->assertSee('data-test="edit-read-only"', false)->assertDontSee('data-test="edit-asset-submit"', false);

    expect(fn () => app(UpdateAsset::class)->handle($this->admin, $asset, ['asset_type_id' => $asset->asset_type_id, 'condition_id' => $asset->condition_id, 'observations' => 'Cambio']))
        ->toThrow(ValidationException::class, __('assets.read_only'))
        ->and(fn () => app(AttachAssetFiles::class)->handle($this->admin, $asset, AssetFileKind::Photo, [UploadedFile::fake()->image('otra.jpg')]))
        ->toThrow(ValidationException::class, __('assets.read_only'))
        ->and(fn () => app(RemoveAssetFile::class)->handle($this->admin, $asset, $photo))
        ->toThrow(ValidationException::class, __('assets.read_only'))
        ->and(fn () => app(AddAssetComponent::class)->handle($this->admin, $asset, ['name' => 'RAM', 'quantity' => 1]))
        ->toThrow(ValidationException::class);

    expect($asset->refresh()->observations)->toBeNull()
        ->and($asset->files()->count())->toBe(1);
});

test('an Administrador registers the loss of an asset, which stops counting as available', function () {
    $kpisBefore = DashboardKpis::current();

    $event = registerEvent($this->asset, ['kind' => 'loss', 'occurred_on' => '2026-09-15', 'description' => 'No apareció en el inventario de setiembre.']);
    $asset = $this->asset->refresh();

    expect($event->kind)->toBe(AssetEventKind::Loss)
        ->and($asset->status)->toBe(AssetStatus::Lost)
        ->and($asset->status->isAvailable())->toBeFalse()
        ->and($asset->current_space_id)->toBe($this->space->id)
        ->and(RecordAssetMovement::allowedTypes($asset))->toBe([])
        ->and(SpaceRow::query()->findOrFail($this->space->id)->asset_count)->toBe(0)
        ->and(DashboardKpis::current()->assets_in_service)->toBe($kpisBefore->assets_in_service - 1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'asset_event', 'entity_id' => $event->id]);

    $this->actingAs($this->admin)->get(route('assets.show', $asset))
        ->assertSeeInOrder([__('assets.show.status_since.lost'), '15/09/2026'])
        ->assertSee('No apareció en el inventario de setiembre.')
        ->assertSee('data-test="edit-asset-button"', false);
});

test('a lost asset is recovered back into service, and only a lost asset can be', function () {
    expect(eventErrors($this->asset, ['kind' => 'recovery']))->toHaveKey('kind');

    registerEvent($this->asset, ['kind' => 'loss']);
    expect(eventErrors($this->asset->refresh(), ['kind' => 'loss']))->toHaveKey('kind')
        ->and(RegisterAssetEvent::allowedKinds($this->asset))->toBe([AssetEventKind::Recovery, AssetEventKind::Decommission]);

    registerEvent($this->asset, ['kind' => 'recovery', 'description' => 'Apareció en la bodega.']);

    expect($this->asset->refresh()->status)->toBe(AssetStatus::InService)
        ->and($this->asset->events()->pluck('kind')->all())->toBe([AssetEventKind::Loss, AssetEventKind::Recovery]);
});

test('a lost asset may be decommissioned', function () {
    registerEvent($this->asset, ['kind' => 'loss']);
    registerEvent($this->asset->refresh(), ['kind' => 'decommission', 'description' => 'No se recuperó.']);

    expect($this->asset->refresh()->status)->toBe(AssetStatus::Decommissioned);
});

test('the Superusuario registers events of an asset of any space', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    Livewire::test('assets.asset-events', ['asset' => $other])->assertSee('data-test="register-event-button"', false);
    registerEvent($other, ['kind' => 'loss'], $superuser);

    expect($other->refresh()->status)->toBe(AssetStatus::Lost);
});

test('an Administrador who does not manage the asset\'s space cannot register its events', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    Livewire::test('assets.asset-events', ['asset' => $other])
        ->assertDontSee('data-test="register-event-button"', false)
        ->call('open')->assertForbidden();

    expect(fn () => registerEvent($other, ['kind' => 'decommission']))->toThrow(AuthorizationException::class)
        ->and($other->refresh()->status)->toBe(AssetStatus::InService);
});

test('roles without assets.register_events cannot register events', function (SystemRole $role) {
    $user = actingAsRole($role);

    Livewire::test('assets.asset-events', ['asset' => $this->asset])
        ->assertDontSee('data-test="register-event-button"', false)
        ->call('save')->assertForbidden();

    expect(fn () => registerEvent($this->asset, ['kind' => 'loss'], $user))->toThrow(AuthorizationException::class)
        ->and(AssetEvent::query()->count())->toBe(0);
})->with([SystemRole::Visualizador, SystemRole::Reportes, SystemRole::Reservante]);

test('everyone who may view the asset sees its events, or that it has none', function () {
    actingAsRole(SystemRole::Visualizador);

    Livewire::test('assets.asset-events', ['asset' => $this->asset])->assertSee(__('assets.events.empty'));

    registerEvent($this->asset, ['kind' => 'loss', 'description' => 'Se perdió en la mudanza.']);

    Livewire::test('assets.asset-events', ['asset' => $this->asset->refresh()])
        ->assertSee('Se perdió en la mudanza.')
        ->assertSee($this->admin->name)
        ->assertDontSee(__('assets.events.empty'));
});
