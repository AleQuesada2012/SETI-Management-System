<?php

// RF_51 · Examinar reserva.

use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->space = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110']);
    $this->responsible = userWithRole(SystemRole::Reservante);
    $this->creator = User::factory()->create(['name' => 'Beto Soto']);

    // Far ahead: PostgreSQL derives the effective status from its own clock, so it stays upcoming.
    $this->reservation = Reservation::factory()
        ->between(Carbon::parse('2030-10-15 10:00'), Carbon::parse('2030-10-15 12:00'))
        ->create([
            'space_id' => $this->space->id, 'title' => 'Tutoría de cálculo', 'notes' => 'Traer marcadores',
            'attendees' => 12, 'responsible_id' => $this->responsible->id, 'created_by' => $this->creator->id,
        ]);
});

test('the detail shows space, date, time, reason, attendees, responsible and current state', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador))
        ->get(route('reservations.show', $this->reservation))
        ->assertOk()
        ->assertSee('LAB-110 · Laboratorio 110')
        ->assertSee(route('spaces.show', $this->space), escape: false)
        ->assertSee('15/10/2030')
        ->assertSee('10:00–12:00')
        ->assertSee('Tutoría de cálculo')
        ->assertSee('Traer marcadores')
        ->assertSee('12')
        ->assertSee($this->responsible->name)
        ->assertSee('Beto Soto')
        ->assertSee('Próxima');
});

test('the responsible sees the modify and delete options; a user who cannot change it does not', function () {
    $this->actingAs($this->responsible)
        ->get(route('reservations.show', $this->reservation))
        ->assertSee(__('reservations.show.update'))
        ->assertSee(__('reservations.show.cancel'));

    $this->actingAs(userWithRole(SystemRole::Reservante))
        ->get(route('reservations.show', $this->reservation))
        ->assertOk()
        ->assertDontSee(__('reservations.show.update'))
        ->assertDontSee(__('reservations.show.cancel'));
});

test('a space manager sees the options for reservations of their space', function () {
    $admin = userWithRole(SystemRole::Administrador);
    $this->space->managers()->attach($admin->id, ['assigned_at' => now()]);

    $this->actingAs($admin)
        ->get(route('reservations.show', $this->reservation))
        ->assertSee(__('reservations.show.update'))
        ->assertSee(__('reservations.show.cancel'));
});

test('a cancelled reservation still opens and says it was cancelled, without change options', function () {
    $this->reservation->forceFill([
        'status' => 'cancelled', 'cancelled_at' => Carbon::parse('2030-10-01 09:30'),
        'cancelled_by' => $this->creator->id, 'cancellation_reason' => 'Se suspendió la tutoría',
    ])->save();

    $this->actingAs($this->responsible)
        ->get(route('reservations.show', $this->reservation))
        ->assertOk()
        ->assertSee(__('reservations.show.cancelled', ['date' => '01/10/2030 09:30', 'by' => 'Beto Soto']))
        ->assertSee('Se suspendió la tutoría')
        ->assertSee('Cancelada')
        ->assertDontSee(__('reservations.show.update'));
});

test('a reservation that does not exist answers 404', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador))
        ->get(route('reservations.show', 999999))
        ->assertNotFound();
});

test('a user without reservations.view cannot examine a reservation', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reservations.show', $this->reservation))
        ->assertForbidden();
});
