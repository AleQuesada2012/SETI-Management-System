<?php

// RF_34 · Eliminar espacio.

use App\Actions\Spaces\DeleteSpace;
use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Reservation;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create(['code' => 'LAB-900', 'name' => 'Laboratorio 900']);
});

test('a manager deletes an empty space after confirming: it is soft-deleted, audited and its code is free again', function () {
    $this->actingAs($this->admin);

    Livewire::test('spaces.delete-space', ['space' => $this->space])
        ->call('open')
        ->assertSet('blockers', [])
        ->assertSee(__('spaces.delete.confirm'))
        ->call('delete')
        ->assertRedirect(route('spaces.index'));

    expect($this->space->refresh()->trashed())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'deleted', 'entity_type' => 'space', 'entity_id' => $this->space->id, 'actor_id' => $this->admin->id]);
    $this->get(route('spaces.show', $this->space->id))->assertNotFound();

    expect(Space::factory()->create(['code' => 'LAB-900', 'name' => 'Laboratorio 900'])->exists)->toBeTrue();
});

test('a space with assets in service or in maintenance cannot be deleted, and the user is told why', function () {
    Asset::factory()->inSpace($this->space)->count(2)->create();
    $this->actingAs($this->admin);

    Livewire::test('spaces.delete-space', ['space' => $this->space])
        ->call('open')
        ->assertSet('blockers', ['tiene 2 activos en servicio o en mantenimiento.'])
        ->assertSee(__('spaces.delete.refused'))
        ->assertDontSee(__('spaces.delete.confirm'));

    expect(fn () => app(DeleteSpace::class)->handle($this->admin, $this->space))->toThrow(ValidationException::class);
    expect($this->space->refresh()->trashed())->toBeFalse();
});

test('lost or decommissioned assets do not block the deletion', function () {
    $asset = Asset::factory()->inSpace($this->space)->create();
    $asset->forceFill(['status' => AssetStatus::Decommissioned])->save();

    app(DeleteSpace::class)->handle($this->admin, $this->space);

    expect($this->space->refresh()->trashed())->toBeTrue();
});

test('a space with confirmed reservations that have not ended cannot be deleted', function () {
    Reservation::factory()->create(['space_id' => $this->space->id]);

    try {
        app(DeleteSpace::class)->handle($this->admin, $this->space);
        $this->fail('The space should not have been deleted.');
    } catch (ValidationException $e) {
        expect($e->errors()['space'])->toBe([__('spaces.delete.refused'), 'tiene 1 reserva confirmada que aún no termina.']);
    }

    expect($this->space->refresh()->trashed())->toBeFalse();
});

test('past and cancelled reservations do not block the deletion', function () {
    Reservation::factory()->between(now()->subDays(3)->setTime(8, 0), now()->subDays(3)->setTime(9, 0))->create(['space_id' => $this->space->id]);
    Reservation::factory()->create(['space_id' => $this->space->id, 'status' => 'cancelled', 'cancelled_at' => now()]);

    app(DeleteSpace::class)->handle($this->admin, $this->space);

    expect($this->space->refresh()->trashed())->toBeTrue();
});

test('the Superusuario deletes any empty space', function () {
    app(DeleteSpace::class)->handle(userWithRole(SystemRole::Superusuario), $this->space);

    expect($this->space->refresh()->trashed())->toBeTrue();
});

test('a space deleted meanwhile is reported instead of failing', function () {
    $this->actingAs($this->admin);
    $component = Livewire::test('spaces.delete-space', ['space' => $this->space]);
    $this->space->delete();

    $component->call('delete')->assertRedirect(route('spaces.index'));
});

test('an Administrador of another space and read-only roles cannot delete it', function (SystemRole $role) {
    $user = actingAsRole($role);

    Livewire::test('spaces.delete-space', ['space' => $this->space])
        ->assertDontSee(__('spaces.delete.button'))
        ->call('delete')
        ->assertForbidden();

    expect(fn () => app(DeleteSpace::class)->handle($user, $this->space))->toThrow(AuthorizationException::class);
    expect($this->space->refresh()->trashed())->toBeFalse();
})->with([SystemRole::Administrador, SystemRole::Visualizador, SystemRole::Reservante]);
