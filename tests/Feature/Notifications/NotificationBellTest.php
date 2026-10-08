<?php

// RF_47, RF_48 · The bell in the top bar: the user's inbox of notifications (ADR-0006).

use App\Enums\SystemRole;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservations\ReservationCreated;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

afterEach(fn () => Carbon::setTestNow());

/** Delivers a reservation.created notification about the given reservation to the user. */
function notifyAboutReservation(User $user, Reservation $reservation, string $title = 'Tutoría de cálculo'): void
{
    $user->notify(new ReservationCreated([
        'reservation_ids' => [$reservation->id],
        'series_id' => null,
        'space' => ['id' => $reservation->space_id, 'code' => 'LAB-110', 'name' => 'Laboratorio 110'],
        'periods' => [['starts_at' => '2030-10-15T10:00:00-06:00', 'ends_at' => '2030-10-15T12:00:00-06:00']],
        'title' => $title,
        'actor' => ['id' => $reservation->created_by, 'name' => 'Beto Reservante'],
    ]));
}

test('the bell shows its own user\'s unread count and notifications, never another user\'s', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $otherUser = userWithRole(SystemRole::Administrador);
    $reservation = Reservation::factory()->create();
    notifyAboutReservation($superuser, $reservation, 'Tutoría de cálculo');
    notifyAboutReservation($superuser, $reservation, 'Reunión de proyecto');
    notifyAboutReservation($otherUser, $reservation, 'Defensa de tesis');

    Livewire::test('notifications.bell')
        ->assertSee('2 sin leer')
        ->assertSee('«Tutoría de cálculo»')
        ->assertSee('«Reunión de proyecto»')
        ->assertDontSee('«Defensa de tesis»');
});

test('every signed-in page has the bell in the top bar', function () {
    actingAsRole(SystemRole::Reservante);

    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSeeLivewire('notifications.bell');
});

test('opening a notification marks it as read and goes to the reservation', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $reservation = Reservation::factory()->create();
    notifyAboutReservation($superuser, $reservation);
    $notification = $superuser->notifications()->sole();

    Livewire::test('notifications.bell')
        ->call('open', $notification->id)
        ->assertRedirect(route('reservations.show', $reservation));

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('nobody opens another user\'s notification', function () {
    actingAsRole(SystemRole::Superusuario);
    $otherUser = userWithRole(SystemRole::Administrador);
    notifyAboutReservation($otherUser, Reservation::factory()->create());
    $notification = $otherUser->notifications()->sole();

    Livewire::test('notifications.bell')
        ->call('open', $notification->id)
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

test('marking all as read clears only the user\'s own unread notifications', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $otherUser = userWithRole(SystemRole::Administrador);
    $reservation = Reservation::factory()->create();
    notifyAboutReservation($superuser, $reservation);
    notifyAboutReservation($superuser, $reservation);
    notifyAboutReservation($otherUser, $reservation);

    Livewire::test('notifications.bell')
        ->call('markAllAsRead')
        ->assertDontSee('sin leer');

    expect($superuser->unreadNotifications()->count())->toBe(0)
        ->and($otherUser->unreadNotifications()->count())->toBe(1);
});

test('read notifications older than 180 days are pruned, unread and recent ones stay', function () {
    $user = userWithRole(SystemRole::Superusuario);
    $reservation = Reservation::factory()->create();
    Carbon::setTestNow('2030-01-01 08:00');
    notifyAboutReservation($user, $reservation, 'Vieja leída');
    notifyAboutReservation($user, $reservation, 'Vieja sin leer');
    $user->notifications()->where('data->title', 'Vieja leída')->update(['read_at' => now()]);
    Carbon::setTestNow('2030-07-01 08:00');
    notifyAboutReservation($user, $reservation, 'Reciente leída');
    $user->notifications()->where('data->title', 'Reciente leída')->update(['read_at' => now()]);

    $this->artisan('model:prune', ['--model' => [Notification::class]])->assertSuccessful();

    expect($user->notifications()->pluck('data')->pluck('title')->sort()->values()->all())
        ->toBe(['Reciente leída', 'Vieja sin leer']);
});
