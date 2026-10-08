<?php

// RF_50 · Eliminar reserva (cancel: the row stays in the history).

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\CreateReservation;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Notifications\Reservations\ReservationCancelled;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Far ahead (Tuesday 15 October 2030) or long past: PostgreSQL derives the effective status the page shows from its
 * own clock, so these dates stay upcoming or finished without freezing time.
 */
function upcomingReservation(Space $space, User $responsible): Reservation
{
    return Reservation::factory()
        ->between(Carbon::parse('2030-10-15 10:00'), Carbon::parse('2030-10-15 12:00'))
        ->create(['space_id' => $space->id, 'responsible_id' => $responsible->id, 'title' => 'Tutoría de cálculo'])
        ->refresh();
}

function cancelAs(User $actor, Reservation $reservation, ?string $reason = null, ?int $readVersion = null): Reservation
{
    return app(CancelReservation::class)->handle($actor, $reservation, $readVersion ?? $reservation->version, $reason);
}

test('the responsible deletes their reservation after confirming: it is cancelled, kept and audited', function () {
    $reservante = actingAsRole(SystemRole::Reservante);
    $space = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110']);
    $reservation = upcomingReservation($space, $reservante);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->set('cancellation_reason', '  Se suspendió la tutoría  ')
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertRedirect(route('reservations.index', ['mes' => '2030-10', 'dia' => '2030-10-15']));

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->cancelled_by)->toBe($reservante->id)
        ->and($reservation->cancelled_at)->not->toBeNull()
        ->and($reservation->cancellation_reason)->toBe('Se suspendió la tutoría');
    $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'entity_type' => 'reservation', 'entity_id' => $reservation->id, 'actor_id' => $reservante->id]);
});

test('a deleted reservation frees its block for a new booking', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $space = Space::factory()->create(['capacity' => 20]);
    $reservation = upcomingReservation($space, $reservante);

    cancelAs($reservante, $reservation);

    app(CreateReservation::class)->handle($reservante, [
        'space_id' => $space->id, 'date' => '2030-10-15', 'start_time' => '10:00', 'end_time' => '12:00',
        'title' => 'Otra tutoría', 'attendees' => 5, 'responsible_id' => $reservante->id, 'notes' => '',
    ]);

    expect(Reservation::query()->confirmed()->where('space_id', $space->id)->pluck('title')->all())->toBe(['Otra tutoría']);
});

test('an Administrador deletes reservations of the spaces they manage only', function () {
    $admin = userWithRole(SystemRole::Administrador);
    $managed = Space::factory()->managedBy($admin)->create();
    $other = Space::factory()->create();
    $responsible = userWithRole(SystemRole::Reservante);

    expect(cancelAs($admin, upcomingReservation($managed, $responsible))->status)->toBe(ReservationStatus::Cancelled)
        ->and(fn () => cancelAs($admin, upcomingReservation($other, $responsible)))->toThrow(AuthorizationException::class);
});

test('a Reservante cannot delete someone else\'s reservation, from the page or the action', function () {
    $intruder = actingAsRole(SystemRole::Reservante);
    $reservation = upcomingReservation(Space::factory()->create(), userWithRole(SystemRole::Reservante));

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->assertDontSeeHtml('data-test="reservation-cancel"')
        ->call('cancel')
        ->assertForbidden();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('a reservation that already started or finished offers no delete option and is refused', function () {
    $reservante = actingAsRole(SystemRole::Reservante);
    $finished = Reservation::factory()
        ->between(Carbon::parse('2020-03-10 10:00'), Carbon::parse('2020-03-10 12:00'))
        ->create(['responsible_id' => $reservante->id])
        ->refresh();

    $this->get(route('reservations.show', $finished))
        ->assertOk()
        ->assertDontSeeHtml('data-test="reservation-cancel"');

    expect(fn () => cancelAs($reservante, $finished))
        ->toThrow(ValidationException::class, __('reservations.cancel.errors.started'));
    expect($finished->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('a reservation already deleted, or changed since the user opened it, is refused with its message', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $space = Space::factory()->create();
    $cancelled = upcomingReservation($space, $reservante);
    cancelAs($reservante, $cancelled);
    $modified = Reservation::factory()
        ->between(Carbon::parse('2030-10-16 10:00'), Carbon::parse('2030-10-16 12:00'))
        ->create(['space_id' => $space->id, 'responsible_id' => $reservante->id]);
    $modified->forceFill(['version' => 2])->save();
    $cancelled->refresh();

    expect(fn () => cancelAs($reservante, $cancelled, readVersion: 1))
        ->toThrow(ValidationException::class, __('reservations.cancel.errors.already_cancelled'))
        ->and(fn () => cancelAs($reservante, $modified, readVersion: 1))
        ->toThrow(ValidationException::class, __('reservations.cancel.errors.changed'));
    expect($modified->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('the reason may not exceed 500 characters', function () {
    actingAsRole(SystemRole::Reservante);
    $reservation = upcomingReservation(Space::factory()->create(), auth()->user());

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->set('cancellation_reason', str_repeat('a', 501))
        ->call('cancel')
        ->assertHasErrors(['cancellation_reason' => 'max']);

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('the Superusuarios and the space\'s managers are notified of the deletion, not its author', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $manager = userWithRole(SystemRole::Administrador);
    $reservante = userWithRole(SystemRole::Reservante);
    $reservation = upcomingReservation(Space::factory()->managedBy($manager)->create(), $reservante);
    Notification::fake();

    cancelAs($reservante, $reservation);

    Notification::assertSentTo([$superuser, $manager], ReservationCancelled::class);
    Notification::assertNotSentTo($reservante, ReservationCancelled::class);
});

test('the manager reads which space and block were freed in their inbox', function () {
    $manager = userWithRole(SystemRole::Administrador);
    $reservante = userWithRole(SystemRole::Reservante);
    $reservante->update(['name' => 'Beto Reservante']);
    $space = Space::factory()->managedBy($manager)->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110']);
    $reservation = upcomingReservation($space, $reservante);

    cancelAs($reservante, $reservation);

    $this->actingAs($manager);
    Livewire::test('notifications.bell')
        ->assertSee('Reserva eliminada en LAB-110 · Laboratorio 110')
        ->assertSee('Beto Reservante eliminó la reserva «Tutoría de cálculo» y liberó el horario: 15/10/2030, de 10:00 a 12:00.');
});
