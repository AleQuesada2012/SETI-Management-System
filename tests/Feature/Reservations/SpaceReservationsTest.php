<?php

// RF_57 · Consultar reservas de un espacio.

use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Queries\Reservations\ReservationSchedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // A month far ahead: PostgreSQL derives the effective status from its own clock, so these stay upcoming.
    Carbon::setTestNow('2030-10-14 09:00');

    $this->space = Space::factory()->create();
    $this->ana = User::factory()->create(['name' => 'Ana Rojas']);

    $this->thisMonth = Reservation::factory()
        ->between(Carbon::parse('2030-10-20 10:00'), Carbon::parse('2030-10-20 12:00'))
        ->create(['space_id' => $this->space->id, 'title' => 'Tutoría de cálculo', 'attendees' => 12, 'responsible_id' => $this->ana->id]);
    $this->nextMonth = Reservation::factory()
        ->between(Carbon::parse('2030-11-03 08:00'), Carbon::parse('2030-11-03 09:30'))
        ->create(['space_id' => $this->space->id, 'title' => 'Defensa de proyecto']);
    Reservation::factory()
        ->between(Carbon::parse('2030-10-21 10:00'), Carbon::parse('2030-10-21 12:00'))
        ->create(['title' => 'Reunión en otro espacio']);
});

afterEach(fn () => Carbon::setTestNow());

test('the space\'s reservations of the current month show date, time, responsible, attendees and status', function () {
    actingAsRole(SystemRole::Visualizador);

    Livewire::test('reservations.space-reservations', ['space' => $this->space])
        ->assertSeeInOrder(['20/10/2030', '10:00–12:00', 'Tutoría de cálculo', 'Ana Rojas', '12', 'Próxima'])
        ->assertSee(route('reservations.show', $this->thisMonth), escape: false)
        ->assertDontSee('Defensa de proyecto')
        ->assertDontSee('Reunión en otro espacio');
});

test('another period can be consulted', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::test('reservations.space-reservations', ['space' => $this->space])
        ->set('from', '2030-11-01')
        ->set('to', '2030-11-30')
        ->call('search')
        ->assertHasNoErrors()
        ->assertSee('Defensa de proyecto')
        ->assertDontSee('Tutoría de cálculo');
});

test('a period without reservations says so', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::test('reservations.space-reservations', ['space' => $this->space])
        ->set('from', '2030-12-01')
        ->set('to', '2030-12-31')
        ->call('search')
        ->assertSee(__('reservations.space_reservations.empty'));
});

test('the final date cannot come before the initial one', function () {
    actingAsRole(SystemRole::Reservante);

    Livewire::test('reservations.space-reservations', ['space' => $this->space])
        ->set('from', '2030-11-30')
        ->set('to', '2030-11-01')
        ->call('search')
        ->assertHasErrors(['to' => 'after_or_equal'])
        ->assertSee(__('reservations.validation.to_after_from'))
        ->assertSee('Tutoría de cálculo');
});

test('cancelled and modified reservations are listed with their state', function () {
    actingAsRole(SystemRole::Visualizador);
    $this->thisMonth->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $this->ana->id])->save();

    Livewire::test('reservations.space-reservations', ['space' => $this->space])
        ->assertSee('Cancelada');

    $this->thisMonth->forceFill(['status' => 'confirmed', 'cancelled_at' => null, 'cancelled_by' => null, 'version' => 2])->save();

    Livewire::test('reservations.space-reservations', ['space' => $this->space])
        ->assertSee(__('reservations.status.modified'));
});

test('a reservation that crosses the period boundary belongs to the period', function () {
    $overnight = Reservation::factory()
        ->between(Carbon::parse('2030-10-31 20:00'), Carbon::parse('2030-11-01 02:00'))
        ->create(['space_id' => $this->space->id]);

    expect(ReservationSchedule::query(['space' => ['values' => [$this->space->id]], 'date' => ['from' => '2030-11-01', 'to' => '2030-11-30']])->pluck('id')->all())
        ->toBe([$overnight->id, $this->nextMonth->id]);
});

test('the list is shown on the space page', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.show', $this->space))
        ->assertOk()
        ->assertSee('Tutoría de cálculo');
});

test('a user without reservations.view cannot consult a space\'s reservations', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('reservations.space-reservations', ['space' => $this->space])
        ->assertDontSee('Tutoría de cálculo')
        ->call('search')
        ->assertForbidden();
});
