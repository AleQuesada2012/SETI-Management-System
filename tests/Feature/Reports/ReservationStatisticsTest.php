<?php

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\CreateReservation;
use App\Actions\Reservations\UpdateReservation;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Queries\Reports\ReservationStatistics;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// RF_83 · reservations of a period by space and status, following registrations, changes and cancellations.

beforeEach(function () {
    // Monday 14 October 2030, 08:00 Costa Rica time.
    Carbon::setTestNow('2030-10-14 08:00');
    $this->lab = Space::factory()->create(['code' => 'LAB-101', 'name' => 'Laboratorio de redes', 'capacity' => 20]);
    $this->room = Space::factory()->create(['code' => 'SAL-01', 'name' => 'Sala de reuniones', 'capacity' => 20]);
});

afterEach(fn () => Carbon::setTestNow());

/** @return array<string, mixed> */
function statisticsReservation(Space $space, string $date, string $start = '10:00', string $end = '11:00'): array
{
    return ['space_id' => $space->id, 'date' => $date, 'start_time' => $start, 'end_time' => $end,
        'title' => 'Reserva de estadística', 'attendees' => 5, 'responsible_id' => auth()->id(), 'notes' => ''];
}

test('the statistics count the reservations of each space and status', function () {
    // reporting.reservations derives the status from the database clock, not the frozen test clock.
    Carbon::setTestNow();
    Reservation::factory()->for($this->lab)->between(now()->subDays(3), now()->subDays(3)->addHour())->create();
    Reservation::factory()->for($this->lab)->between(now()->subDays(2), now()->subDays(2)->addHour())->create();
    Reservation::factory()->for($this->lab)->between(now()->addDay(), now()->addDay()->addHour())->create();
    Reservation::factory()->for($this->room)->between(now()->subHour(), now()->addHour())->create();
    Reservation::factory()->for($this->room)->between(now()->addDays(2), now()->addDays(2)->addHour())
        ->create(['status' => ReservationStatus::Cancelled, 'cancelled_at' => now()]);
    actingAsRole(SystemRole::Reportes);

    $summary = ReservationStatistics::summary();

    expect($summary['total'])->toBe(5)
        ->and(array_column($summary['statuses'], 'count', 'id'))->toBe(['upcoming' => 1, 'in_progress' => 1, 'finished' => 2, 'cancelled' => 1])
        ->and($summary['spaces'][0])->toMatchArray(['label' => 'LAB-101 · Laboratorio de redes', 'count' => 3,
            'by_status' => ['upcoming' => 1, 'in_progress' => 0, 'finished' => 2, 'cancelled' => 0]])
        ->and($summary['spaces'][1])->toMatchArray(['label' => 'SAL-01 · Sala de reuniones', 'count' => 2]);
    $this->get(route('reports.statistics.reservations'))->assertOk()
        ->assertSee('Reservas en el período: 5')->assertSeeInOrder(['LAB-101 · Laboratorio de redes', 'SAL-01 · Sala de reuniones']);
});

test('the period counts reservations on their Costa Rica start day, both days included', function () {
    Reservation::factory()->for($this->lab)->between(Carbon::parse('2030-10-01 00:30'), Carbon::parse('2030-10-01 01:30'))->create();
    Reservation::factory()->for($this->lab)->between(Carbon::parse('2030-10-31 23:00'), Carbon::parse('2030-10-31 23:30'))->create();
    Reservation::factory()->for($this->lab)->between(Carbon::parse('2030-09-30 23:00'), Carbon::parse('2030-09-30 23:30'))->create();
    actingAsRole(SystemRole::Reportes);

    Livewire::test('pages::reports.reservation-statistics')
        ->assertSee('Reservas en el período: 3')
        ->set('filters.starts_on', ['from' => '2030-10-01', 'to' => '2030-10-31'])->assertSee('Reservas en el período: 2')
        ->set('filters.starts_on', ['from' => '2030-12-01'])->assertSee(__('reports.reservation_statistics.empty'))
        ->call('clearFilters')->assertSet('filters', [])->assertSee('Reservas en el período: 3');
});

test('registering, modifying and cancelling reservations change the statistics on the next refresh', function () {
    $reservante = actingAsRole(SystemRole::Reportes, SystemRole::Reservante);
    $page = Livewire::test('pages::reports.reservation-statistics')->assertSee(__('reports.reservation_statistics.empty'));

    $reservation = app(CreateReservation::class)->handle($reservante, statisticsReservation($this->lab, '2030-10-15'));
    $page->call('$refresh')->assertSee('Reservas en el período: 1');
    expect(ReservationStatistics::summary()['spaces'][0]['id'])->toBe($this->lab->id);

    $reservation->refresh();
    $reservation = app(UpdateReservation::class)->handle($reservante, $reservation, $reservation->version,
        statisticsReservation($this->room, '2030-10-16') + ['modification_reason' => 'Cambio de sala']);
    $page->call('$refresh')->assertSee('SAL-01 · Sala de reuniones');
    expect(array_column(ReservationStatistics::summary()['spaces'], 'id'))->toBe([$this->room->id]);

    $reservation->refresh();
    app(CancelReservation::class)->handle($reservante, $reservation, $reservation->version);
    $page->call('$refresh');
    expect(array_column(ReservationStatistics::summary()['statuses'], 'count', 'id'))->toMatchArray(['upcoming' => 0, 'cancelled' => 1]);
});

test('invalid reservation periods produce Spanish feedback instead of statistics', function () {
    actingAsRole(SystemRole::Reportes);
    Livewire::test('pages::reports.reservation-statistics')
        ->set('filters.starts_on', ['from' => '2030-10-31', 'to' => '2030-10-01'])
        ->assertSee('La fecha final no puede ser anterior a la inicial.')->assertDontSee('Reservas en el período');
});

test('reservation statistics reject users without report permission', function () {
    actingAsRole(SystemRole::Reservante);
    $this->get(route('reports.statistics.reservations'))->assertForbidden();
});
