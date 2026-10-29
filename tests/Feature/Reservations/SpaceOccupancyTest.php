<?php

// RF_59 · Consultar ocupación de espacios por período.

use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** A confirmed reservation of $space from $startsAt to $endsAt (Costa Rica time). */
function occupancyReservation(Space $space, string $startsAt, string $endsAt): Reservation
{
    return Reservation::factory()->between(Carbon::parse($startsAt), Carbon::parse($endsAt))->create(['space_id' => $space->id]);
}

/** The occupancy page opened on a period, as a link or an edited URL would open it. */
function occupancyPage(string $from, string $to): Testable
{
    return Livewire::withQueryParams(['filters' => ['date' => ['from' => $from, 'to' => $to]]])->test('pages::reservations.occupancy');
}

/** Outside October 2026, so the periods below come from the URL and not from the current-month default. */
beforeEach(function () {
    Carbon::setTestNow('2027-01-15 10:00');
    actingAsRole(SystemRole::Reservante);
});

test('the occupancy lists the spaces with confirmed reservations in the period and how many each had', function () {
    $lab = Space::factory()->create(['code' => 'LAB-01', 'name' => 'Laboratorio de redes']);
    $classroom = Space::factory()->create(['code' => 'AUL-02', 'name' => 'Aula 2']);
    $september = Space::factory()->create(['code' => 'SAL-03', 'name' => 'Sala de septiembre']);
    Space::factory()->create(['code' => 'SAL-04', 'name' => 'Sala sin reservas']);

    occupancyReservation($lab, '2026-10-05 08:00', '2026-10-05 10:00');
    occupancyReservation($lab, '2026-10-20 13:00', '2026-10-20 14:30');
    occupancyReservation($lab, '2026-11-02 08:00', '2026-11-02 10:00');
    occupancyReservation($lab, '2026-10-21 08:00', '2026-10-21 10:00')
        ->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => User::factory()->create()->id])->save();
    occupancyReservation($classroom, '2026-10-12 09:00', '2026-10-12 11:00');
    occupancyReservation(Space::factory()->create(['code' => 'SAL-05', 'name' => 'Sala de reuniones']), '2026-10-14 08:00', '2026-10-14 08:45');
    occupancyReservation($september, '2026-09-28 09:00', '2026-09-28 11:00');

    occupancyPage('2026-10-01', '2026-10-31')
        ->assertSeeInOrder([
            '3 espacios con reservas',
            'LAB-01 · Laboratorio de redes', '2', '3 h 30 min',
            'AUL-02 · Aula 2', '1', '2 h',
            'SAL-05 · Sala de reuniones', '1', '45 min',
        ])
        ->assertDontSee('Sala de septiembre')
        ->assertDontSee('Sala sin reservas');
});

test('the period includes its first and last days and a reservation counts on the day it starts', function () {
    $lab = Space::factory()->create(['code' => 'LAB-01']);
    $classroom = Space::factory()->create(['code' => 'AUL-02', 'name' => 'Aula que empezó antes']);

    occupancyReservation($lab, '2026-10-01 07:00', '2026-10-01 08:00');
    occupancyReservation($lab, '2026-10-31 23:00', '2026-11-01 01:00');
    occupancyReservation($classroom, '2026-09-30 23:00', '2026-10-01 01:00');

    occupancyPage('2026-10-01', '2026-10-31')
        ->assertSeeInOrder(['1 espacio con reservas', 'LAB-01', '2', '3 h'])
        ->assertDontSee('Aula que empezó antes');
});

test('the page opens on the current month', function () {
    Carbon::setTestNow('2026-10-15 10:00');
    occupancyReservation(Space::factory()->create(['name' => 'Reservada en octubre']), '2026-10-20 08:00', '2026-10-20 10:00');
    occupancyReservation(Space::factory()->create(['name' => 'Reservada en septiembre']), '2026-09-20 08:00', '2026-09-20 10:00');

    Livewire::test('pages::reservations.occupancy')
        ->assertSet('filters', ['date' => ['from' => '2026-10-01', 'to' => '2026-10-31']])
        ->assertSee('Octubre de 2026')
        ->assertDontSee('Mes actual')
        ->assertSee('Reservada en octubre')
        ->assertDontSee('Reservada en septiembre');
});

test('the arrows move the period one whole month at a time, across years', function () {
    occupancyReservation(Space::factory()->create(['name' => 'Reservada en diciembre']), '2026-12-10 08:00', '2026-12-10 10:00');

    Livewire::test('pages::reservations.occupancy')
        ->assertSee('Enero de 2027')
        ->call('previousMonth')
        ->assertSet('filters', ['date' => ['from' => '2026-12-01', 'to' => '2026-12-31']])
        ->assertSee('Diciembre de 2026')
        ->assertSee('Reservada en diciembre')
        ->call('nextMonth')
        ->call('nextMonth')
        ->assertSet('filters', ['date' => ['from' => '2027-02-01', 'to' => '2027-02-28']])
        ->assertSee('Febrero de 2027');
});

test('a period chosen by hand is not a month, and the arrows start from the month it begins in', function () {
    occupancyPage('2026-10-10', '2026-10-20')
        ->assertSee('Período personalizado')
        ->call('nextMonth')
        ->assertSet('filters', ['date' => ['from' => '2026-11-01', 'to' => '2026-11-30']])
        ->assertSee('Noviembre de 2026');
});

test('Mes actual goes back to the current month', function () {
    occupancyPage('2026-10-01', '2026-10-31')
        ->assertSee('Mes actual')
        ->call('thisMonth')
        ->assertSet('filters', ['date' => ['from' => '2027-01-01', 'to' => '2027-01-31']])
        ->assertSee('Enero de 2027');
});

test('the spaces can be ordered by reservations or by booked hours, most or fewest first', function () {
    $frequent = Space::factory()->create(['name' => 'Sala de reservas cortas']);
    $long = Space::factory()->create(['name' => 'Sala de una reserva larga']);
    occupancyReservation($frequent, '2026-10-05 08:00', '2026-10-05 09:00');
    occupancyReservation($frequent, '2026-10-06 08:00', '2026-10-06 09:00');
    occupancyReservation($long, '2026-10-05 08:00', '2026-10-05 12:00');

    occupancyPage('2026-10-01', '2026-10-31')
        ->assertSeeInOrder(['Sala de reservas cortas', 'Sala de una reserva larga'])
        ->set('order', 'fewest_reservations')
        ->assertSeeHtmlInOrder(['Sala de una reserva larga', 'Sala de reservas cortas'])
        ->set('order', 'most_hours')
        ->assertSeeHtmlInOrder(['Sala de una reserva larga', 'Sala de reservas cortas'])
        ->set('order', 'fewest_hours')
        ->assertSeeHtmlInOrder(['Sala de reservas cortas', 'Sala de una reserva larga']);
});

test('a final date before the initial one is not a valid period', function () {
    occupancyReservation(Space::factory()->create(['name' => 'Laboratorio reservado']), '2026-10-05 08:00', '2026-10-05 10:00');

    occupancyPage('2026-10-01', '2026-10-31')
        ->assertSee('Laboratorio reservado')
        ->set('filters.date.to', '2026-09-30')
        ->assertSee('El período seleccionado no es válido')
        ->assertSee('La fecha final no puede ser anterior a la fecha inicial.')
        ->assertDontSee('Laboratorio reservado');
});

test('an invalid date or an unknown filter in the URL is reported instead of ignored', function () {
    occupancyPage('2026-02-30', '2026-03-31')
        ->assertSee('La fecha inicial no es una fecha válida.');

    Livewire::withQueryParams(['filters' => ['space' => ['values' => [1]]]])
        ->test('pages::reservations.occupancy')
        ->assertSee('El filtro «space» no existe.');
});

test('the page says so when no space has reservations in the period', function () {
    occupancyReservation(Space::factory()->create(), '2026-09-05 08:00', '2026-09-05 10:00');

    occupancyPage('2026-10-01', '2026-10-31')
        ->assertSee('Ningún espacio registra reservas en el período seleccionado.');
});

test('the calendar links to the occupancy page', function () {
    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSee(route('reservations.occupancy'), escape: false);

    $this->get(route('reservations.occupancy'))->assertOk();
});

test('a user without access to the reservations cannot open the occupancy', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reservations.occupancy'))->assertForbidden();
});
