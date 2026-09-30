<?php

// RF_55 · Consultar disponibilidad de un espacio.

use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->space = Space::factory()->create();
    $this->day = Carbon::tomorrow();

    Reservation::factory()
        ->between($this->day->copy()->setTime(10, 0), $this->day->copy()->setTime(12, 0))
        ->create(['space_id' => $this->space->id, 'title' => 'Tutoría de cálculo']);
});

function checkAvailability(Space $space, string $date, string $start, string $end): mixed
{
    return Livewire::test('reservations.availability-check', ['space' => $space])
        ->set('date', $date)
        ->set('start_time', $start)
        ->set('end_time', $end)
        ->call('check');
}

test('a free block is reported as available and offers to reserve it', function () {
    actingAsRole(SystemRole::Reservante);

    checkAvailability($this->space, $this->day->toDateString(), '13:00', '14:00')
        ->assertHasNoErrors()
        ->assertSee(__('reservations.availability_check.available'))
        ->assertSee($this->day->format('d/m/Y').', de 13:00 a 14:00')
        ->assertSee(__('reservations.availability_check.reserve'))
        ->assertDontSee(__('reservations.availability_check.unavailable'));
});

test('an overlapping block is reported as unavailable with the reservation that blocks it', function () {
    actingAsRole(SystemRole::Visualizador);

    checkAvailability($this->space, $this->day->toDateString(), '11:00', '13:00')
        ->assertSee(__('reservations.availability_check.unavailable'))
        ->assertSee('10:00–12:00')
        ->assertSee('Tutoría de cálculo')
        ->assertDontSee(__('reservations.availability_check.reserve'));
});

test('a block that starts when another ends is available ([start, end) semantics)', function () {
    actingAsRole(SystemRole::Reservante);

    checkAvailability($this->space, $this->day->toDateString(), '12:00', '13:00')
        ->assertSee(__('reservations.availability_check.available'));
});

test('cancelled reservations and other spaces do not block a block', function () {
    actingAsRole(SystemRole::Reservante);
    Reservation::query()->update([
        'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => User::factory()->create()->id,
    ]);
    Reservation::factory()
        ->between($this->day->copy()->setTime(10, 0), $this->day->copy()->setTime(12, 0))
        ->create();

    checkAvailability($this->space, $this->day->toDateString(), '10:00', '12:00')
        ->assertSee(__('reservations.availability_check.available'));
});

test('the end time must come after the start time', function () {
    actingAsRole(SystemRole::Reservante);

    checkAvailability($this->space, $this->day->toDateString(), '14:00', '13:00')
        ->assertHasErrors(['end_time' => 'after'])
        ->assertSee(__('reservations.validation.end_after_start'))
        ->assertDontSee(__('reservations.availability_check.available'));
});

test('a space that cannot be reserved says so and offers no reservation', function () {
    actingAsRole(SystemRole::Reservante);
    $office = Space::factory()->office()->create();

    checkAvailability($office, $this->day->toDateString(), '13:00', '14:00')
        ->assertSee(__('reservations.availability_check.not_reservable'))
        ->assertDontSee(__('reservations.availability_check.reserve'));
});

test('the check is shown on the space page', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.show', $this->space))
        ->assertOk()
        ->assertSee(__('reservations.availability_check.submit'));
});

test('a user without reservations.view cannot check availability', function () {
    $this->actingAs(User::factory()->create());

    checkAvailability($this->space, $this->day->toDateString(), '13:00', '14:00')
        ->assertForbidden();
});
