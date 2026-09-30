<?php

// RF_44 · Asignar estado a un laboratorio (and its duplicate RF_58 · Cambiar estado de un espacio).

use App\Actions\Spaces\ChangeSpaceStatus;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\SpaceStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->available = SpaceStatus::query()->where('name', 'Disponible')->firstOrFail();
    $this->maintenance = SpaceStatus::query()->where('name', 'En mantenimiento')->firstOrFail();
    $this->space->status()->associate($this->available)->save();
});

test('a manager sets the status, sees it on the space page, and it is audited', function () {
    $this->actingAs($this->admin);

    Livewire::test('spaces.change-status', ['space' => $this->space])
        ->call('choose', $this->maintenance->id)
        ->assertHasNoErrors()
        ->assertRedirect(route('spaces.show', $this->space));

    expect($this->space->refresh()->space_status_id)->toBe($this->maintenance->id);
    $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'entity_id' => $this->space->id, 'actor_id' => $this->admin->id]);

    $this->get(route('spaces.show', $this->space))->assertSeeInOrder([$this->space->name, 'En mantenimiento']);
});

test('the Superusuario sets the status of any space', function () {
    app(ChangeSpaceStatus::class)->handle(userWithRole(SystemRole::Superusuario), $this->space, $this->maintenance);

    expect($this->space->refresh()->space_status_id)->toBe($this->maintenance->id);
});

test('a status without reservations warns first when confirmed reservations are ahead', function () {
    $this->actingAs($this->admin);
    Reservation::factory()->count(2)->sequence(
        ['starts_at' => now()->addDay()->setTime(8, 0), 'ends_at' => now()->addDay()->setTime(9, 0)],
        ['starts_at' => now()->addDays(2)->setTime(8, 0), 'ends_at' => now()->addDays(2)->setTime(9, 0)],
    )->create(['space_id' => $this->space->id]);

    $component = Livewire::test('spaces.change-status', ['space' => $this->space])
        ->call('choose', $this->maintenance->id)
        ->assertSet('pendingStatusId', $this->maintenance->id)
        ->assertSet('affectedCount', 2)
        ->assertSee('2 reservas confirmadas');

    expect($this->space->refresh()->space_status_id)->toBe($this->available->id);

    $component->call('confirm')->assertHasNoErrors();

    expect($this->space->refresh()->space_status_id)->toBe($this->maintenance->id)
        ->and(Reservation::query()->confirmed()->where('space_id', $this->space->id)->count())->toBe(2);
});

test('the Action refuses an unconfirmed change that affects reservations', function () {
    Reservation::factory()->create(['space_id' => $this->space->id]);

    expect(fn () => app(ChangeSpaceStatus::class)->handle($this->admin, $this->space, $this->maintenance))
        ->toThrow(ValidationException::class);
    expect($this->space->refresh()->space_status_id)->toBe($this->available->id);
});

test('past and cancelled reservations do not trigger the warning', function () {
    Reservation::factory()->between(now()->subDays(2)->setTime(8, 0), now()->subDays(2)->setTime(9, 0))
        ->create(['space_id' => $this->space->id]);
    Reservation::factory()->create(['space_id' => $this->space->id, 'status' => 'cancelled', 'cancelled_at' => now()]);

    expect(ChangeSpaceStatus::affectedReservations($this->space, $this->maintenance))->toBe(0);
});

test('an Administrador cannot set the status of a space they do not manage', function () {
    $otherAdmin = actingAsRole(SystemRole::Administrador);

    Livewire::test('spaces.change-status', ['space' => $this->space])
        ->assertDontSee(__('spaces.status.button'))
        ->call('choose', $this->maintenance->id)
        ->assertForbidden();

    expect(fn () => app(ChangeSpaceStatus::class)->handle($otherAdmin, $this->space, $this->maintenance))
        ->toThrow(AuthorizationException::class);
});

test('roles other than Superusuario and Administrador are blocked with an authorization error', function (SystemRole $role) {
    actingAsRole($role);

    Livewire::test('spaces.change-status', ['space' => $this->space])
        ->call('choose', $this->maintenance->id)
        ->assertForbidden();

    expect($this->space->refresh()->space_status_id)->toBe($this->available->id);
})->with([SystemRole::Visualizador, SystemRole::Reservante, SystemRole::Reportes]);
