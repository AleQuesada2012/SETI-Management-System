<?php

// RF_52 · Historial de reservas.

use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Queries\Reservations\ReservationSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** A reservation of $space for $user from $startsAt, two hours long. */
function historyReservation(Space $space, User $user, string $startsAt, string $title): Reservation
{
    $start = Carbon::parse($startsAt);

    return Reservation::factory()
        ->between($start, $start->copy()->addHours(2))
        ->create(['space_id' => $space->id, 'responsible_id' => $user->id, 'title' => $title]);
}

/** PostgreSQL derives the effective status from its own clock: 2020 is finished and 2030 is upcoming. */
function cancelHistoryReservation(Reservation $reservation): void
{
    $reservation->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $reservation->responsible_id])->save();
}

test('the history lists the user\'s past, future, cancelled and modified reservations with their details', function () {
    $user = actingAsRole(SystemRole::Reservante);
    $space = Space::factory()->create(['code' => 'LAB-07', 'name' => 'Laboratorio de redes']);

    historyReservation($space, $user, '2020-03-10 08:00', 'Tutoría pasada');
    historyReservation($space, $user, '2030-05-20 14:00', 'Defensa futura');
    cancelHistoryReservation(historyReservation($space, $user, '2030-06-01 10:00', 'Charla suspendida'));
    $modified = historyReservation($space, $user, '2030-07-01 09:00', 'Reunión movida');
    $modified->forceFill(['version' => 2])->save();

    Livewire::test('pages::reservations.history')
        ->assertSeeInOrder(['01/07/2030', '09:00–11:00', 'LAB-07 · Laboratorio de redes', 'Reunión movida', 'Próxima', 'Modificada'])
        ->assertSeeInOrder(['01/06/2030', 'Charla suspendida', 'Cancelada'])
        ->assertSeeInOrder(['20/05/2030', 'Defensa futura', 'Próxima'])
        ->assertSeeInOrder(['10/03/2020', 'Tutoría pasada', 'Finalizada'])
        ->assertSee(route('reservations.show', $modified), escape: false);
});

test('the history can be ordered from the oldest reservation', function () {
    $user = actingAsRole(SystemRole::Reservante);
    $space = Space::factory()->create();
    historyReservation($space, $user, '2020-03-10 08:00', 'Tutoría pasada');
    historyReservation($space, $user, '2030-05-20 14:00', 'Defensa futura');

    Livewire::test('pages::reservations.history')
        ->assertSeeInOrder(['Defensa futura', 'Tutoría pasada'])
        ->set('order', 'asc')
        ->assertSeeHtmlInOrder(['Tutoría pasada', 'Defensa futura']);
});

test('a Reservante sees the reservations they made or are responsible for, not other people\'s', function () {
    $user = actingAsRole(SystemRole::Reservante);
    $space = Space::factory()->create();
    historyReservation($space, $user, '2030-05-20 14:00', 'Soy responsable');
    Reservation::factory()->create(['created_by' => $user->id, 'title' => 'La hice para otra persona']);
    Reservation::factory()->create(['space_id' => $space->id, 'title' => 'Reserva ajena']);

    Livewire::test('pages::reservations.history')
        ->assertSee('Soy responsable')
        ->assertSee('La hice para otra persona')
        ->assertDontSee('Reserva ajena');
});

test('an Administrador also sees the reservations of the spaces they manage, not of other spaces', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    Reservation::factory()->create(['space_id' => Space::factory()->managedBy($admin)->create()->id, 'title' => 'En mi laboratorio']);
    Reservation::factory()->create(['title' => 'En otro laboratorio']);

    Livewire::test('pages::reservations.history')
        ->assertSee('En mi laboratorio')
        ->assertDontSee('En otro laboratorio');
});

test('the Superusuario sees every reservation', function () {
    actingAsRole(SystemRole::Superusuario);
    Reservation::factory()->create(['title' => 'Reserva de cualquiera']);

    Livewire::test('pages::reservations.history')->assertSee('Reserva de cualquiera');
});

test('the history can be filtered by status', function () {
    $user = actingAsRole(SystemRole::Reservante);
    $space = Space::factory()->create();
    historyReservation($space, $user, '2030-05-20 14:00', 'Defensa futura');
    cancelHistoryReservation(historyReservation($space, $user, '2030-06-01 10:00', 'Charla suspendida'));
    historyReservation($space, $user, '2030-07-01 09:00', 'Reunión movida')->forceFill(['version' => 2])->save();

    Livewire::test('pages::reservations.history')
        ->set('filters.status.values', ['cancelled'])
        ->assertSee('Charla suspendida')
        ->assertDontSee('Defensa futura')
        ->assertDontSee('Reunión movida')
        ->set('filters.status.values', ['cancelled', 'modified'])
        ->assertSee('Charla suspendida')
        ->assertSee('Reunión movida')
        ->assertDontSee('Defensa futura');
});

test('the history can be filtered by a date range', function () {
    $user = actingAsRole(SystemRole::Reservante);
    $space = Space::factory()->create();
    historyReservation($space, $user, '2030-05-20 14:00', 'En mayo');
    historyReservation($space, $user, '2030-06-01 10:00', 'En junio');

    Livewire::test('pages::reservations.history')
        ->set('filters.date', ['from' => '2030-06-01', 'to' => '2030-06-30'])
        ->assertSee('En junio')
        ->assertDontSee('En mayo');
});

test('a final date before the initial one blocks the list with its message', function () {
    $user = actingAsRole(SystemRole::Reservante);
    historyReservation(Space::factory()->create(), $user, '2030-05-20 14:00', 'En mayo');

    Livewire::test('pages::reservations.history')
        ->set('filters.date', ['from' => '2030-06-30', 'to' => '2030-06-01'])
        ->assertSee(__('reservations.validation.to_after_from'))
        ->assertDontSee('En mayo')
        ->call('clearFilters')
        ->assertSee('En mayo');
});

test('a status that does not exist is reported, not ignored', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::test('pages::reservations.history', ['filters' => ['status' => ['values' => ['pending']]]])
        ->assertSee('El filtro «Estado» incluye una opción que no existe.');
});

test('the history says when there are no reservations, and when none match the filters', function () {
    $user = actingAsRole(SystemRole::Reservante);

    Livewire::test('pages::reservations.history')->assertSee(__('reservations.history.empty'));

    historyReservation(Space::factory()->create(), $user, '2030-05-20 14:00', 'Defensa futura');

    Livewire::test('pages::reservations.history')
        ->set('filters.status.values', ['cancelled'])
        ->assertSee(__('reservations.history.no_results'));
});

test('the filters reject unknown keys and spaces that do not exist', function () {
    expect(fn () => ReservationSchedule::validate(['owner' => 3]))->toThrow(ValidationException::class, 'El filtro «owner» no existe.')
        ->and(fn () => ReservationSchedule::validate(['space' => ['values' => [999999]]]))->toThrow(ValidationException::class, 'El filtro «Espacio» incluye una opción que no existe.');
});

test('the filters are stored normalized', function () {
    expect(ReservationSchedule::validate([
        'status' => ['values' => ['modified', 'cancelled', 'cancelled']],
        'date' => ['from' => '2030-06-01', 'to' => ''],
        'space' => ['values' => []],
    ]))->toBe(['status' => ['values' => ['cancelled', 'modified']], 'date' => ['from' => '2030-06-01']]);
});

test('the history opens from the reservations calendar', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('reservations.index'))->assertSee(route('reservations.history'), escape: false);
    $this->get(route('reservations.history'))->assertOk();
});

test('a user without reservations.view cannot open the history', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reservations.history'))->assertForbidden();
});
