<?php

// RF_47 · Notificación de reserva de espacio a Superusuario.
// RF_48 · Notificación de reserva de espacio a Usuario Administrador asignado.

use App\Actions\Reservations\CreateReservation;
use App\Enums\SystemRole;
use App\Models\Space;
use App\Models\User;
use App\Notifications\Reservations\ReservationCreated;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow('2030-10-14 08:00'));

afterEach(fn () => Carbon::setTestNow());

/** Reserves the space on Tuesday 15 October 2030, 10:00–12:00, unless overridden. */
function bookSpace(User $actor, Space $space, array $overrides = []): void
{
    app(CreateReservation::class)->handle($actor, [
        'space_id' => $space->id,
        'date' => '2030-10-15',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'title' => 'Tutoría de cálculo',
        'attendees' => 10,
        'responsible_id' => $actor->id,
        'notes' => '',
        ...$overrides,
    ]);
}

test('a reservation notifies the active Superusuarios and the managers of that space, not its author', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $deactivatedSuperuser = User::factory()->deactivated()->create();
    $deactivatedSuperuser->assignRole(SystemRole::Superusuario->value);
    $manager = userWithRole(SystemRole::Administrador);
    $managerElsewhere = userWithRole(SystemRole::Administrador);
    $author = userWithRole(SystemRole::Administrador);
    $space = Space::factory()->managedBy($manager, $author)->create(['capacity' => 20]);
    Space::factory()->managedBy($managerElsewhere)->create();
    $reservante = userWithRole(SystemRole::Reservante);
    Notification::fake();

    bookSpace($author, $space);

    Notification::assertSentTo([$superuser, $manager], ReservationCreated::class);
    Notification::assertNotSentTo([$author, $deactivatedSuperuser, $managerElsewhere, $reservante], ReservationCreated::class);
});

test('a space without managers notifies only the Superusuarios', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $reservante = userWithRole(SystemRole::Reservante);
    $space = Space::factory()->create(['capacity' => 20]);
    Notification::fake();

    bookSpace($reservante, $space);

    Notification::assertSentTo($superuser, ReservationCreated::class);
    Notification::assertCount(1);
});

test('the notification stores a snapshot of the space, the period, the reason and the author', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $reservante = userWithRole(SystemRole::Reservante);
    $reservante->update(['name' => 'Beto Reservante']);
    $space = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110', 'capacity' => 20]);

    bookSpace($reservante, $space);

    $notification = $superuser->notifications()->sole();
    $space->update(['name' => 'Laboratorio renombrado']);

    expect($notification->type)->toBe('reservation.created')
        ->and($notification->read_at)->toBeNull()
        ->and($notification->data)->toMatchArray([
            'space' => ['id' => $space->id, 'code' => 'LAB-110', 'name' => 'Laboratorio 110'],
            'periods' => [['starts_at' => '2030-10-15T10:00:00-06:00', 'ends_at' => '2030-10-15T12:00:00-06:00']],
            'title' => 'Tutoría de cálculo',
            'actor' => ['id' => $reservante->id, 'name' => 'Beto Reservante'],
        ]);
});

test('the Superusuario reads the space and the dates of the reservation in their inbox', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $reservante = userWithRole(SystemRole::Reservante);
    $reservante->update(['name' => 'Beto Reservante']);
    $space = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110', 'capacity' => 20]);
    bookSpace($reservante, $space);

    $this->actingAs($superuser);

    Livewire::test('notifications.bell')
        ->assertSee('Nueva reserva en LAB-110 · Laboratorio 110')
        ->assertSee('Beto Reservante reservó el espacio para «Tutoría de cálculo»: 15/10/2030, de 10:00 a 12:00.');
});

test('the notification is pushed live on the recipient\'s private channel', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $reservante = userWithRole(SystemRole::Reservante);
    $space = Space::factory()->create(['capacity' => 20]);
    Event::fake([BroadcastNotificationCreated::class]);

    bookSpace($reservante, $space);

    Event::assertDispatched(BroadcastNotificationCreated::class, fn (BroadcastNotificationCreated $event): bool => $event->notifiable->is($superuser)
        && $event->broadcastOn()[0]->name === "private-App.Models.User.{$superuser->id}"
        && $event->broadcastType() === 'reservation.created');
});

test('a refused reservation notifies nobody', function () {
    userWithRole(SystemRole::Superusuario);
    $reservante = userWithRole(SystemRole::Reservante);
    $space = Space::factory()->create(['capacity' => 20]);
    bookSpace($reservante, $space);
    Notification::fake();

    expect(fn () => bookSpace($reservante, $space, ['start_time' => '11:00', 'end_time' => '13:00']))
        ->toThrow(ValidationException::class);

    Notification::assertNothingSent();
});
