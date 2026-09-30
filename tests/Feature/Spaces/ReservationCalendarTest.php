<?php

// RF_45 · Revisar calendario de espacios.

use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Queries\Spaces\ReservationCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-01 09:00', 'America/Costa_Rica'));
    $this->space = Space::factory()->create(['code' => 'LAB-501', 'name' => 'Laboratorio 501']);
    $this->responsible = User::factory()->create(['name' => 'Ana Rojas']);
});

afterEach(fn () => Carbon::setTestNow());

function reserve(Space $space, User $responsible, string $from, string $to, array $attributes = []): Reservation
{
    return Reservation::factory()
        ->between(CarbonImmutable::parse($from, 'America/Costa_Rica'), CarbonImmutable::parse($to, 'America/Costa_Rica'))
        ->create(['space_id' => $space->id, 'responsible_id' => $responsible->id, ...$attributes]);
}

test('the calendar shows the days of the month and moves between months', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::withQueryParams(['mes' => '2026-10'])
        ->test('pages::reservations.index')
        ->assertSee('Octubre de 2026')
        ->assertSeeHtml('data-test="day-2026-10-31"')
        ->assertDontSeeHtml('data-test="day-2026-11-01"')
        ->call('nextMonth')
        ->assertSet('month', '2026-11')
        ->assertSee('Noviembre de 2026')
        ->call('previousMonth')
        ->call('previousMonth')
        ->assertSet('month', '2026-09')
        ->assertSee('Septiembre de 2026');
});

test('an invalid month falls back to the current month', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::withQueryParams(['mes' => 'nada'])
        ->test('pages::reservations.index')
        ->assertSet('month', '2026-10');
});

test('days with confirmed reservations are marked and free days are not', function () {
    actingAsRole(SystemRole::Visualizador);
    reserve($this->space, $this->responsible, '2026-10-05 08:00', '2026-10-05 10:00');
    reserve($this->space, $this->responsible, '2026-10-06 08:00', '2026-10-06 10:00', ['status' => 'cancelled', 'cancelled_at' => now()]);

    Livewire::withQueryParams(['mes' => '2026-10'])
        ->test('pages::reservations.index')
        ->assertSeeHtml('data-test="day-2026-10-05" data-reserved="yes"')
        ->assertSeeHtml('data-test="day-2026-10-06" data-reserved="no"')
        ->assertSeeHtml('data-test="day-2026-10-07" data-reserved="no"');
});

test('selecting a day shows which spaces are reserved, when and for whom', function () {
    actingAsRole(SystemRole::Reservante);
    reserve($this->space, $this->responsible, '2026-10-05 08:00', '2026-10-05 10:30', ['title' => 'Clase de redes']);
    $other = Space::factory()->create(['code' => 'SAL-09']);
    reserve($other, $this->responsible, '2026-10-06 13:00', '2026-10-06 14:00');

    Livewire::withQueryParams(['mes' => '2026-10'])
        ->test('pages::reservations.index')
        ->call('selectDay', '2026-10-05')
        ->assertSet('day', '2026-10-05')
        ->assertSeeInOrder(['08:00', '10:30', 'LAB-501', 'Laboratorio 501', 'Clase de redes', 'Reservado para Ana Rojas'])
        ->assertDontSee('SAL-09');
});

test('a free day says every space is free', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::withQueryParams(['mes' => '2026-10', 'dia' => '2026-10-07'])
        ->test('pages::reservations.index')
        ->assertSee(__('spaces.calendar.day_free'));
});

test('days are Costa Rica days, and a reservation across midnight marks both days', function () {
    reserve($this->space, $this->responsible, '2026-10-09 23:30', '2026-10-09 23:59');
    reserve($this->space, $this->responsible, '2026-10-12 22:00', '2026-10-13 02:00');

    $days = ReservationCalendar::month(CarbonImmutable::parse('2026-10-01'));

    expect(array_keys($days))->toBe(['2026-10-09', '2026-10-12', '2026-10-13']);
});

test('a reservation ending exactly at midnight does not mark the next day', function () {
    reserve($this->space, $this->responsible, '2026-10-14 20:00', '2026-10-15 00:00');

    expect(array_keys(ReservationCalendar::month(CarbonImmutable::parse('2026-10-01'))))->toBe(['2026-10-14']);
});

test('a user without permission to view reservations cannot open the calendar', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reservations.index'))->assertForbidden();
});
