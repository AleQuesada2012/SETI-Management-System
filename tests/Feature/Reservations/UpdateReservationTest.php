<?php

// RF_49 · Modificar reserva.

use App\Actions\Reservations\UpdateReservation;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Notifications\Reservations\ReservationMovedIn;
use App\Notifications\Reservations\ReservationMovedOut;
use App\Notifications\Reservations\ReservationUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    // Monday 14 October 2030, 08:00: the reservations below are on Tuesday 15 and the dates are a Sunday (20) or past.
    Carbon::setTestNow('2030-10-14 08:00');

    $this->space = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110', 'capacity' => 20]);
});

afterEach(fn () => Carbon::setTestNow());

/** Tuesday 15 October 2030, 10:00–12:00, far enough ahead for PostgreSQL's clock to call it upcoming too. */
function modifiableReservation(Space $space, User $responsible, array $attributes = []): Reservation
{
    return Reservation::factory()
        ->between(Carbon::parse('2030-10-15 10:00'), Carbon::parse('2030-10-15 12:00'))
        ->create(['space_id' => $space->id, 'responsible_id' => $responsible->id, 'title' => 'Tutoría de cálculo', 'attendees' => 10, ...$attributes])
        ->refresh();
}

/** The form input of the reservation as it is, with overrides. */
function modificationInput(Reservation $reservation, array $overrides = []): array
{
    return [
        'space_id' => $reservation->space_id,
        'date' => $reservation->starts_at->format('Y-m-d'),
        'start_time' => $reservation->starts_at->format('H:i'),
        'end_time' => $reservation->ends_at->format('H:i'),
        'title' => $reservation->title,
        'attendees' => $reservation->attendees,
        'responsible_id' => $reservation->responsible_id,
        'notes' => (string) $reservation->notes,
        'modification_reason' => '',
        ...$overrides,
    ];
}

function modifyAs(User $actor, Reservation $reservation, array $overrides = [], ?int $readVersion = null): Reservation
{
    return app(UpdateReservation::class)->handle($actor, $reservation, $readVersion ?? $reservation->version, modificationInput($reservation, $overrides));
}

test('the responsible changes the date and time from the form: the reservation is marked as modified and audited', function () {
    $reservante = actingAsRole(SystemRole::Reservante);
    $reservation = modifiableReservation($this->space, $reservante);

    Livewire::test('pages::reservations.edit', ['reservation' => $reservation])
        ->assertSet('date', '2030-10-15')
        ->assertSet('start_time', '10:00')
        ->set('date', '2030-10-16')
        ->set('start_time', '14:00')
        ->set('end_time', '15:30')
        ->set('modification_reason', '  Cambió el horario de la tutoría  ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('reservations.show', $reservation));

    $reservation->refresh();
    expect($reservation->starts_at->format('Y-m-d H:i'))->toBe('2030-10-16 14:00')
        ->and($reservation->ends_at->format('Y-m-d H:i'))->toBe('2030-10-16 15:30')
        ->and($reservation->version)->toBe(2)
        ->and($reservation->wasModified())->toBeTrue()
        ->and($reservation->modification_reason)->toBe('Cambió el horario de la tutoría')
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed);
    $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'entity_type' => 'reservation', 'entity_id' => $reservation->id, 'actor_id' => $reservante->id]);

    $this->get(route('reservations.show', $reservation))
        ->assertOk()
        ->assertSee('Modificada')
        ->assertSee('Cambió el horario de la tutoría');
});

test('the responsible and the other details change while the block stays, without overlapping itself', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $newResponsible = User::factory()->create();
    $reservation = modifiableReservation($this->space, $reservante);

    $modified = modifyAs($reservante, $reservation, [
        'responsible_id' => $newResponsible->id,
        'title' => 'Tutoría de física',
        'attendees' => 18,
        'notes' => 'Traer calculadora',
    ]);

    expect($modified->responsible_id)->toBe($newResponsible->id)
        ->and($modified->title)->toBe('Tutoría de física')
        ->and($modified->attendees)->toBe(18)
        ->and($modified->notes)->toBe('Traer calculadora')
        ->and($modified->starts_at->format('Y-m-d H:i'))->toBe('2030-10-15 10:00')
        ->and($modified->version)->toBe(2);
});

test('the reservation moves to another space', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $otherSpace = Space::factory()->create(['capacity' => 30]);
    $reservation = modifiableReservation($this->space, $reservante);

    $modified = modifyAs($reservante, $reservation, ['space_id' => $otherSpace->id, 'attendees' => 25]);

    expect($modified->space_id)->toBe($otherSpace->id)
        ->and($modified->attendees)->toBe(25)
        ->and(Reservation::query()->confirmed()->where('space_id', $this->space->id)->exists())->toBeFalse();
});

test('an Administrador modifies reservations of the spaces they manage only', function () {
    $admin = userWithRole(SystemRole::Administrador);
    $managed = Space::factory()->managedBy($admin)->create();
    $responsible = userWithRole(SystemRole::Reservante);

    expect(modifyAs($admin, modifiableReservation($managed, $responsible), ['title' => 'Revisión'])->title)->toBe('Revisión')
        ->and(fn () => modifyAs($admin, modifiableReservation($this->space, $responsible), ['title' => 'Revisión']))
        ->toThrow(AuthorizationException::class);
});

test('a Reservante cannot modify someone else\'s reservation, from the page or the action', function () {
    $intruder = actingAsRole(SystemRole::Reservante);
    $reservation = modifiableReservation($this->space, userWithRole(SystemRole::Reservante));

    $this->get(route('reservations.show', $reservation))->assertOk()->assertDontSeeHtml('data-test="reservation-update"');
    $this->get(route('reservations.edit', $reservation))->assertForbidden();

    expect(fn () => modifyAs($intruder, $reservation, ['title' => 'Otra cosa']))->toThrow(AuthorizationException::class)
        ->and($reservation->refresh()->version)->toBe(1);
});

test('a Visualizador cannot open the form', function () {
    actingAsRole(SystemRole::Visualizador);
    $reservation = modifiableReservation($this->space, userWithRole(SystemRole::Reservante));

    $this->get(route('reservations.edit', $reservation))->assertForbidden();
});

test('a reservation that already started or finished offers no modify option and is refused', function () {
    $reservante = actingAsRole(SystemRole::Reservante);
    $finished = Reservation::factory()
        ->between(Carbon::parse('2020-03-10 10:00'), Carbon::parse('2020-03-10 12:00'))
        ->create(['responsible_id' => $reservante->id])
        ->refresh();

    $this->get(route('reservations.show', $finished))->assertOk()->assertDontSeeHtml('data-test="reservation-update"');
    Livewire::test('pages::reservations.edit', ['reservation' => $finished])->assertRedirect(route('reservations.show', $finished));

    expect(fn () => modifyAs($reservante, $finished, ['date' => '2030-10-16']))
        ->toThrow(ValidationException::class, __('reservations.edit.errors.started'));
});

test('a deleted reservation, or one changed since the user opened it, is refused with its message', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $cancelled = modifiableReservation($this->space, $reservante);
    $cancelled->forceFill(['status' => ReservationStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $reservante->id])->save();
    $changed = modifiableReservation($this->space, $reservante, ['starts_at' => '2030-10-16 10:00', 'ends_at' => '2030-10-16 12:00']);
    $changed->forceFill(['version' => 2])->save();

    expect(fn () => modifyAs($reservante, $cancelled, ['title' => 'Otra cosa']))
        ->toThrow(ValidationException::class, __('reservations.edit.errors.cancelled'))
        ->and(fn () => modifyAs($reservante, $changed, ['title' => 'Otra cosa'], readVersion: 1))
        ->toThrow(ValidationException::class, __('reservations.edit.errors.changed'));
    expect($changed->refresh()->title)->toBe('Tutoría de cálculo');
});

test('saving without changes is refused', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $reservation = modifiableReservation($this->space, $reservante);

    expect(fn () => modifyAs($reservante, $reservation, ['modification_reason' => 'Nada']))
        ->toThrow(ValidationException::class, __('reservations.edit.errors.unchanged'))
        ->and($reservation->refresh()->version)->toBe(1);
});

test('the new values follow the rules of a new booking, as Spanish messages under their field', function (Closure $overrides, string $field, string $message) {
    $reservante = userWithRole(SystemRole::Reservante);
    $reservation = modifiableReservation($this->space, $reservante);
    Reservation::factory()
        ->between(Carbon::parse('2030-10-15 13:00'), Carbon::parse('2030-10-15 15:00'))
        ->create(['space_id' => $this->space->id]);

    try {
        modifyAs($reservante, $reservation, $overrides());
        $this->fail('The modification should have been refused.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field)
            ->and($e->errors()[$field][0])->toBe($message);
    }

    expect($reservation->refresh()->version)->toBe(1);
})->with([
    'overlapping another reservation' => [fn () => ['start_time' => '11:00', 'end_time' => '14:00'], 'start_time', 'El espacio ya está reservado en ese horario. Revisa las reservas del espacio o el calendario y elige otro horario.'],
    'in the past' => [fn () => ['date' => '2030-10-14', 'start_time' => '07:00', 'end_time' => '09:00'], 'start_time', 'La reserva debe comenzar después de este momento.'],
    'off the 15-minute slots' => [fn () => ['start_time' => '10:05'], 'start_time', 'Las horas de inicio y fin deben ir en franjas de 15 minutos.'],
    'on a Sunday' => [fn () => ['date' => '2030-10-20'], 'start_time', 'Solo se puede reservar los días lunes, martes, miércoles, jueves, viernes y sábado, de 07:00 a 22:00.'],
    'more people than the new space holds' => [fn () => ['space_id' => Space::factory()->create(['capacity' => 5])->id], 'attendees', 'La cantidad de personas supera la capacidad del espacio (5).'],
    'a space that is not reservable' => [fn () => ['space_id' => Space::factory()->office()->create()->id], 'space_id', 'Este espacio no se puede reservar por su tipo o su estado actual.'],
    'a reason longer than 500 characters' => [fn () => ['title' => 'Otra', 'modification_reason' => str_repeat('a', 501)], 'modification_reason', 'El campo motivo de la modificación no puede tener más de 500 caracteres.'],
]);

test('back-to-back with another reservation is allowed', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $reservation = modifiableReservation($this->space, $reservante);
    Reservation::factory()
        ->between(Carbon::parse('2030-10-15 13:00'), Carbon::parse('2030-10-15 15:00'))
        ->create(['space_id' => $this->space->id]);

    expect(modifyAs($reservante, $reservation, ['start_time' => '12:00', 'end_time' => '13:00'])->ends_at->format('H:i'))->toBe('13:00');
});

test('the database has the last word when another booking takes the new block first', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $other = User::factory()->create();
    $reservation = modifiableReservation($this->space, $reservante);

    // Another request books the new block after our free check and before our update.
    Reservation::updating(function () use ($other): void {
        DB::table('reservations')->insert([
            'space_id' => $this->space->id, 'starts_at' => '2030-10-16 10:30', 'ends_at' => '2030-10-16 11:30',
            'title' => 'Reserva simultánea', 'attendees' => 2, 'responsible_id' => $other->id, 'created_by' => $other->id,
        ]);
    });

    try {
        modifyAs($reservante, $reservation, ['date' => '2030-10-16']);
        $this->fail('The overlapping update should have been refused.');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['start_time' => [__('db.reservations_no_overlap')]]);
    }

    expect($reservation->refresh()->version)->toBe(1)
        ->and(Reservation::query()->count())->toBe(1);
});

test('a modification in the same space notifies its Superusuarios and managers, not its author', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $manager = userWithRole(SystemRole::Administrador);
    $reservante = userWithRole(SystemRole::Reservante);
    $space = Space::factory()->managedBy($manager)->create(['capacity' => 20]);
    $reservation = modifiableReservation($space, $reservante);
    Notification::fake();

    modifyAs($reservante, $reservation, ['start_time' => '14:00', 'end_time' => '16:00']);

    Notification::assertSentTo([$superuser, $manager], ReservationUpdated::class);
    Notification::assertNotSentTo($reservante, ReservationUpdated::class);
    Notification::assertNotSentTo($manager, ReservationMovedOut::class);
});

test('a move notifies the managers of the space it left and of the new one; the Superusuario gets both', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $leftManager = userWithRole(SystemRole::Administrador);
    $newManager = userWithRole(SystemRole::Administrador);
    $bothManager = userWithRole(SystemRole::Administrador);
    $reservante = userWithRole(SystemRole::Reservante);
    $left = Space::factory()->managedBy($leftManager, $bothManager)->create(['capacity' => 20]);
    $new = Space::factory()->managedBy($newManager, $bothManager)->create(['capacity' => 20]);
    $reservation = modifiableReservation($left, $reservante);
    Notification::fake();

    modifyAs($reservante, $reservation, ['space_id' => $new->id]);

    Notification::assertSentTo([$superuser, $leftManager, $bothManager], ReservationMovedOut::class);
    Notification::assertSentTo([$superuser, $newManager, $bothManager], ReservationMovedIn::class);
    Notification::assertNotSentTo($newManager, ReservationMovedOut::class);
    Notification::assertNotSentTo($leftManager, ReservationMovedIn::class);
    Notification::assertNotSentTo($reservante, ReservationMovedOut::class);
    Notification::assertNotSentTo($superuser, ReservationUpdated::class);
});

test('the managers read what changed, where it went and why in their inbox', function () {
    $leftManager = userWithRole(SystemRole::Administrador);
    $newManager = userWithRole(SystemRole::Administrador);
    $reservante = userWithRole(SystemRole::Reservante);
    $reservante->update(['name' => 'Beto Reservante']);
    $left = Space::factory()->managedBy($leftManager)->create(['code' => 'LAB-201', 'name' => 'Laboratorio 201', 'capacity' => 20]);
    $new = Space::factory()->managedBy($newManager)->create(['code' => 'LAB-202', 'name' => 'Laboratorio 202', 'capacity' => 20]);
    $reservation = modifiableReservation($left, $reservante);

    modifyAs($reservante, $reservation, ['space_id' => $new->id, 'start_time' => '14:00', 'end_time' => '16:00', 'modification_reason' => 'Se dañó el proyector']);

    $this->actingAs($leftManager);
    Livewire::test('notifications.bell')
        ->assertSee('Se liberó un horario en LAB-201 · Laboratorio 201')
        ->assertSee('Beto Reservante movió la reserva «Tutoría de cálculo» a LAB-202 · Laboratorio 202 y liberó el 15/10/2030, de 10:00 a 12:00. Motivo: Se dañó el proyector');

    $this->actingAs($newManager);
    Livewire::test('notifications.bell')
        ->assertSee('Reserva movida a LAB-202 · Laboratorio 202')
        ->assertSee('Beto Reservante movió la reserva «Tutoría de cálculo» desde LAB-201 · Laboratorio 201: 15/10/2030, de 14:00 a 16:00.');
});
