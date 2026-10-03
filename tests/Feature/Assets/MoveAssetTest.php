<?php

// RF_06 · Traslado de activo entre espacios, and RF_25 · Registrar movimientos de inventario, which close together.

use App\Actions\Assets\RecordAssetMovement;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create(['code' => 'LAB-101', 'name' => 'Laboratorio de redes']);
    $this->destination = Space::factory()->create(['code' => 'LAB-202', 'name' => 'Laboratorio de electrónica']);
    $this->asset = Asset::factory()->inSpace($this->space)->create();
});

/**
 * Calls the action and returns its validation errors, failing if it succeeds.
 *
 * @param  array<string, mixed>  $input
 * @return array<string, list<string>>
 */
function movementErrors(Asset $asset, array $input): array
{
    try {
        app(RecordAssetMovement::class)->handle(test()->admin, $asset, $input);
        test()->fail('The movement was registered.');
    } catch (ValidationException $e) {
        return $e->errors();
    }
}

test('an Administrador transfers an asset of their space to another space and is told where it went', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::assets.move', ['asset' => $this->asset])
        ->assertSet('type', MovementType::Transfer->value)
        ->set('to_space_id', $this->destination->id)
        ->set('reason', 'Reubicación del laboratorio')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.move.done.transfer', [
            'asset' => $this->asset->code,
            'space' => 'LAB-202 · Laboratorio de electrónica',
        ])])
        ->assertRedirect(route('assets.show', $this->asset));

    expect($this->asset->refresh()->current_space_id)->toBe($this->destination->id);

    $movement = AssetMovement::query()->where('asset_id', $this->asset->id)->latest('id')->firstOrFail();

    expect($movement->type)->toBe(MovementType::Transfer)
        ->and($movement->from_space_id)->toBe($this->space->id)
        ->and($movement->to_space_id)->toBe($this->destination->id)
        ->and($movement->moved_by)->toBe($this->admin->id)
        ->and($movement->moved_at->diffInSeconds(now(), true))->toBeLessThan(5)
        ->and($movement->reason)->toBe('Reubicación del laboratorio');

    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'asset_movement', 'entity_id' => $movement->id, 'actor_id' => $this->admin->id]);
});

test('the detail page shows the movements, newest first, with their date, type, spaces, user and reason', function () {
    $this->freezeTime();
    $this->actingAs($this->admin);
    app(RecordAssetMovement::class)->handle($this->admin, $this->asset, ['type' => 'transfer', 'to_space_id' => $this->destination->id, 'reason' => 'Préstamo al curso']);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee(__('assets.show.sections.movements'))
        ->assertSeeInOrder([
            now()->format('d/m/Y H:i'), 'Traslado', 'LAB-101 · Laboratorio de redes', 'LAB-202 · Laboratorio de electrónica', $this->admin->name, 'Préstamo al curso',
            'Ingreso', __('assets.show.movements.outside'), 'LAB-101 · Laboratorio de redes', __('assets.show.movements.system'), 'Registro inicial',
        ]);
});

test('an asset that exits SETI\'s spaces can later enter another one', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::assets.move', ['asset' => $this->asset])
        ->set('type', MovementType::Exit->value)
        ->assertDontSee(__('assets.create.choose'))
        ->set('reason', 'Reparación externa')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.move.done.exit', [
            'asset' => $this->asset->code,
            'space' => 'LAB-101 · Laboratorio de redes',
        ])]);

    expect($this->asset->refresh()->current_space_id)->toBeNull();

    // The managers of the space it last left still answer for it (Agent-DB.md §8.1).
    Livewire::test('pages::assets.move', ['asset' => $this->asset])
        ->assertSee(__('assets.move.outside'))
        ->assertSet('type', MovementType::Entry->value)
        ->set('to_space_id', $this->destination->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->asset->refresh()->current_space_id)->toBe($this->destination->id)
        ->and($this->asset->movements()->pluck('type')->all())->toBe([MovementType::Entry, MovementType::Exit, MovementType::Entry]);
});

test('a movement may be registered with an earlier date', function () {
    $this->travel(2)->days();
    $movedAt = now()->subDay()->startOfMinute();

    app(RecordAssetMovement::class)->handle($this->admin, $this->asset, [
        'type' => 'transfer',
        'to_space_id' => $this->destination->id,
        'moved_at' => $movedAt->format(RecordAssetMovement::MOVED_AT_FORMAT),
    ]);

    expect($this->asset->movements()->reorder()->latest('id')->first()->moved_at->equalTo($movedAt))->toBeTrue();
});

test('the Superusuario moves an asset of any space', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);

    $this->get(route('assets.show', $this->asset))->assertSee('data-test="move-asset-button"', false)->assertDontSee('data-test="move-denied"', false);

    app(RecordAssetMovement::class)->handle($superuser, $this->asset, ['type' => 'transfer', 'to_space_id' => $this->destination->id]);

    expect($this->asset->refresh()->current_space_id)->toBe($this->destination->id);
});

test('an Administrador who does not manage the asset\'s space is denied and told why', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace($this->destination)->create();

    $this->get(route('assets.show', $other))
        ->assertOk()
        ->assertDontSee('data-test="move-asset-button"', false)
        ->assertSee('data-test="move-denied"', false)
        ->assertSee(__('assets.move.not_manager'));
    $this->get(route('assets.move', $other))->assertForbidden();

    expect(fn () => app(RecordAssetMovement::class)->handle($this->admin, $other, ['type' => 'transfer', 'to_space_id' => $this->space->id]))
        ->toThrow(AuthorizationException::class)
        ->and($other->refresh()->current_space_id)->toBe($this->destination->id);
});

test('roles without assets.move cannot register movements', function (SystemRole $role) {
    $user = actingAsRole($role);

    $this->get(route('assets.move', $this->asset))->assertForbidden();

    expect(fn () => app(RecordAssetMovement::class)->handle($user, $this->asset, ['type' => 'transfer', 'to_space_id' => $this->destination->id]))
        ->toThrow(AuthorizationException::class)
        ->and($this->asset->refresh()->current_space_id)->toBe($this->space->id);
})->with([SystemRole::Visualizador, SystemRole::Reportes, SystemRole::Reservante]);

test('those who may view but not move the asset see neither the button nor a denial', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee(__('assets.show.sections.movements'))
        ->assertDontSee('data-test="move-asset-button"', false)
        ->assertDontSee('data-test="move-denied"', false);
});

test('the movement is validated with a message on its field', function (Closure $input, string $field, ?string $message) {
    $errors = movementErrors($this->asset, $input($this));

    expect($errors)->toHaveKey($field);
    if ($message !== null) {
        expect($errors[$field][0])->toBe($message);
    }
    expect($this->asset->movements()->count())->toBe(1);
})->with([
    'a transfer without a destination' => [fn ($t) => ['type' => 'transfer'], 'to_space_id', null],
    'a transfer to the space it is in' => [fn ($t) => ['type' => 'transfer', 'to_space_id' => $t->space->id], 'to_space_id', 'El activo ya está en ese espacio: elige otro destino.'],
    'a deleted destination' => [fn ($t) => ['type' => 'transfer', 'to_space_id' => tap(Space::factory()->create())->delete()->id], 'to_space_id', null],
    'an exit without a reason' => [fn ($t) => ['type' => 'exit'], 'reason', null],
    'an entry while it is in a space' => [fn ($t) => ['type' => 'entry', 'to_space_id' => $t->destination->id], 'type', 'El estado del activo no permite ese tipo de movimiento.'],
    'a future date' => [fn ($t) => ['type' => 'transfer', 'to_space_id' => $t->destination->id, 'moved_at' => now()->addHour()->format('Y-m-d\TH:i')], 'moved_at', 'La fecha del movimiento no puede ser futura.'],
    'a date that is not a date' => [fn ($t) => ['type' => 'transfer', 'to_space_id' => $t->destination->id, 'moved_at' => 'ayer'], 'moved_at', null],
]);

test('the form shows validation errors on its fields', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::assets.move', ['asset' => $this->asset])
        ->call('save')
        ->assertHasErrors(['to_space_id' => 'required']);
});

test('a movement dated before the asset\'s latest one is rejected by the database with its Spanish message', function () {
    $errors = movementErrors($this->asset, [
        'type' => 'transfer',
        'to_space_id' => $this->destination->id,
        'moved_at' => now()->subDay()->format(RecordAssetMovement::MOVED_AT_FORMAT),
    ]);

    expect($errors['moved_at'][0])->toBe(__('db.asset_movements_chronological'))
        ->and($this->asset->refresh()->current_space_id)->toBe($this->space->id);
});

test('a move from a space the asset already left is rejected by the database', function () {
    $stale = Asset::query()->findOrFail($this->asset->id);
    app(RecordAssetMovement::class)->handle($this->admin, $this->asset, ['type' => 'transfer', 'to_space_id' => $this->destination->id]);

    // Someone else moved it after this copy was loaded: the trigger checks the real origin.
    $errors = movementErrors($stale, ['type' => 'exit', 'reason' => 'Préstamo']);

    expect($errors['form'][0])->toBe(__('db.asset_movements_origin_matches'))
        ->and($this->asset->refresh()->current_space_id)->toBe($this->destination->id);
});

test('a lost asset cannot be moved until it is recovered', function () {
    $this->actingAs($this->admin);
    $stale = Asset::query()->findOrFail($this->asset->id);
    $this->asset->forceFill(['status' => AssetStatus::Lost])->save();

    Livewire::test('pages::assets.move', ['asset' => $this->asset])
        ->assertSee(__('assets.move.cannot_move.lost'))
        ->assertDontSee('data-test="move-asset-submit"', false);

    expect(movementErrors($this->asset, ['type' => 'transfer', 'to_space_id' => $this->destination->id]))->toHaveKey('type')
        // A copy loaded before the loss passes validation; the database still refuses it.
        ->and(movementErrors($stale, ['type' => 'transfer', 'to_space_id' => $this->destination->id])['form'][0])
        ->toBe(__('db.asset_movements_asset_rules'))
        ->and($this->asset->refresh()->current_space_id)->toBe($this->space->id);
});

test('a decommissioned asset may only exit', function () {
    $this->actingAs($this->admin);
    $this->asset->forceFill(['status' => AssetStatus::Decommissioned])->save();

    Livewire::test('pages::assets.move', ['asset' => $this->asset])
        ->assertSet('type', MovementType::Exit->value)
        ->set('reason', 'Desecho')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->asset->refresh()->current_space_id)->toBeNull();

    $this->get(route('assets.move', $this->asset))->assertOk()->assertSee(__('assets.move.cannot_move.decommissioned'));
});
