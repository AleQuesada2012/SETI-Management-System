<?php

// RF_46 · Reservar espacio.

use App\Actions\Reservations\CreateReservation;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    // Monday 14 October 2030, 08:00: the dates below are a Tuesday (15), a Saturday (19) and a Sunday (20).
    Carbon::setTestNow('2030-10-14 08:00');

    $this->space = Space::factory()->create(['name' => 'Laboratorio de redes', 'capacity' => 20]);
});

afterEach(fn () => Carbon::setTestNow());

/** The form input of a valid reservation, with overrides. */
function reservationInput(Space $space, array $overrides = []): array
{
    return [
        'space_id' => $space->id,
        'date' => '2030-10-15',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'title' => 'Tutoría de cálculo',
        'attendees' => 15,
        'responsible_id' => auth()->id() ?? User::factory()->create()->id,
        'notes' => '',
        ...$overrides,
    ];
}

function reserveAs(User $user, array $input): Reservation
{
    return app(CreateReservation::class)->handle($user, $input);
}

test('a Reservante reserves a space from the form and is taken to the reservation', function () {
    $reservante = actingAsRole(SystemRole::Reservante);
    $responsible = User::factory()->create(['name' => 'Ana Rojas']);

    $component = Livewire::test('pages::reservations.create')
        ->set('space_id', (string) $this->space->id)
        ->set('date', '2030-10-15')
        ->set('start_time', '10:00')
        ->set('end_time', '12:00')
        ->set('title', 'Tutoría de cálculo')
        ->set('attendees', 15)
        ->set('responsible_id', (string) $responsible->id)
        ->call('save');

    $reservation = Reservation::query()->sole();

    $component->assertHasNoErrors()->assertRedirect(route('reservations.show', $reservation));
    expect($reservation->space_id)->toBe($this->space->id)
        ->and($reservation->starts_at->format('Y-m-d H:i'))->toBe('2030-10-15 10:00')
        ->and($reservation->ends_at->format('Y-m-d H:i'))->toBe('2030-10-15 12:00')
        ->and($reservation->title)->toBe('Tutoría de cálculo')
        ->and($reservation->attendees)->toBe(15)
        ->and($reservation->responsible_id)->toBe($responsible->id)
        ->and($reservation->created_by)->toBe($reservante->id)
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed);

    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'reservation', 'entity_id' => $reservation->id, 'actor_id' => $reservante->id]);
});

test('the form offers only reservable spaces and takes the block chosen in the availability check', function () {
    actingAsRole(SystemRole::Reservante);
    Space::factory()->office()->create(['name' => 'Oficina de SETI']);
    Space::factory()->withStatus('Inhabilitado')->create(['name' => 'Laboratorio cerrado']);

    $this->get(route('reservations.create', ['space' => $this->space->id, 'date' => '2030-10-15', 'start' => '13:00', 'end' => '14:00']))
        ->assertOk()
        ->assertSee('Laboratorio de redes')
        ->assertDontSee('Oficina de SETI')
        ->assertDontSee('Laboratorio cerrado');

    Livewire::withQueryParams(['space' => $this->space->id, 'date' => '2030-10-15', 'start' => '13:00', 'end' => '14:00'])
        ->test('pages::reservations.create')
        ->assertSet('space_id', (string) $this->space->id)
        ->assertSet('date', '2030-10-15')
        ->assertSet('start_time', '13:00')
        ->assertSet('end_time', '14:00');
});

test('an overlapping reservation of the same space is refused, a back-to-back one is not', function () {
    $user = userWithRole(SystemRole::Reservante);
    reserveAs($user, reservationInput($this->space, ['responsible_id' => $user->id]));

    expect(fn () => reserveAs($user, reservationInput($this->space, ['responsible_id' => $user->id, 'start_time' => '11:00', 'end_time' => '13:00'])))
        ->toThrow(ValidationException::class, __('reservations.create.errors.overlap'));

    $next = reserveAs($user, reservationInput($this->space, ['responsible_id' => $user->id, 'start_time' => '12:00', 'end_time' => '13:00']));
    $elsewhere = reserveAs($user, reservationInput(Space::factory()->create(['capacity' => 20]), ['responsible_id' => $user->id]));

    expect(Reservation::query()->count())->toBe(3)
        ->and($next->starts_at->format('H:i'))->toBe('12:00')
        ->and($elsewhere->exists)->toBeTrue();
});

test('the database has the last word when another booking wins the race', function () {
    $user = userWithRole(SystemRole::Reservante);
    $other = User::factory()->create();

    // Another request books the same block after our free check and before our insert.
    Reservation::creating(function () use ($other): void {
        DB::table('reservations')->insert([
            'space_id' => $this->space->id, 'starts_at' => '2030-10-15 10:30', 'ends_at' => '2030-10-15 11:30',
            'title' => 'Reserva simultánea', 'attendees' => 2, 'responsible_id' => $other->id, 'created_by' => $other->id,
        ]);
    });

    try {
        reserveAs($user, reservationInput($this->space, ['responsible_id' => $user->id]));
        $this->fail('The overlapping insert should have been refused.');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['start_time' => [__('db.reservations_no_overlap')]]);
    }

    // Our transaction (and the simulated booking inside it) rolled back: nothing half-written remains.
    expect(Reservation::query()->exists())->toBeFalse();
});

test('the rules of the diagram come back as Spanish messages under their field', function (array $overrides, string $field, string $message) {
    $user = userWithRole(SystemRole::Reservante);

    try {
        reserveAs($user, reservationInput($this->space, ['responsible_id' => $user->id, ...$overrides]));
        $this->fail('The reservation should have been refused.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field)
            ->and($e->errors()[$field][0])->toBe($message);
    }

    expect(Reservation::query()->exists())->toBeFalse();
})->with([
    'end before start' => [['start_time' => '12:00', 'end_time' => '11:00'], 'end_time', 'La hora de fin debe ser posterior a la hora de inicio.'],
    'more people than capacity' => [['attendees' => 21], 'attendees', 'La cantidad de personas supera la capacidad del espacio (20).'],
    'already started' => [['date' => '2030-10-14', 'start_time' => '07:00', 'end_time' => '09:00'], 'start_time', 'La reserva debe comenzar después de este momento.'],
    'before opening' => [['start_time' => '06:30', 'end_time' => '08:00'], 'start_time', 'Solo se puede reservar los días lunes, martes, miércoles, jueves, viernes y sábado, de 07:00 a 22:00.'],
    'after closing' => [['start_time' => '21:00', 'end_time' => '22:30'], 'start_time', 'Solo se puede reservar los días lunes, martes, miércoles, jueves, viernes y sábado, de 07:00 a 22:00.'],
    'on a Sunday' => [['date' => '2030-10-20'], 'start_time', 'Solo se puede reservar los días lunes, martes, miércoles, jueves, viernes y sábado, de 07:00 a 22:00.'],
    'without a reason' => [['title' => '   '], 'title', 'El campo motivo es obligatorio.'],
]);

test('a Saturday inside the allowed hours can be reserved', function () {
    $user = userWithRole(SystemRole::Reservante);

    expect(reserveAs($user, reservationInput($this->space, ['responsible_id' => $user->id, 'date' => '2030-10-19', 'start_time' => '07:00', 'end_time' => '22:00']))->exists)
        ->toBeTrue();
});

test('a space that is not reservable is refused', function (Space $space) {
    $user = userWithRole(SystemRole::Reservante);

    expect(fn () => reserveAs($user, reservationInput($space, ['responsible_id' => $user->id])))
        ->toThrow(ValidationException::class, __('reservations.create.errors.not_reservable'));
})->with([
    'an office' => fn () => Space::factory()->office()->create(),
    'a space in maintenance' => fn () => Space::factory()->withStatus('En mantenimiento')->create(),
]);

test('a deactivated user cannot be the responsible', function () {
    $user = userWithRole(SystemRole::Reservante);
    $deactivated = User::factory()->deactivated()->create();

    expect(fn () => reserveAs($user, reservationInput($this->space, ['responsible_id' => $deactivated->id])))
        ->toThrow(ValidationException::class);
});

test('roles without reservations.create cannot reserve', function (SystemRole $role) {
    $user = userWithRole($role);

    expect(fn () => reserveAs($user, reservationInput($this->space, ['responsible_id' => $user->id])))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($user)->get(route('reservations.create'))->assertForbidden();
})->with([SystemRole::Visualizador, SystemRole::Reportes]);
