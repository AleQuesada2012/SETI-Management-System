<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Notifications\Reservations\ReservationCreated;
use App\Notifications\SpaceRecipients;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * RF_46 · Reserve a space for one block of time on one date, following the RF_46 diagram: the space is reservable,
 * the block is inside the allowed hours (config/reservations.php), the attendees fit the capacity, and the space is
 * free. The free check gives a friendly message; the reservations_no_overlap constraint stays the final judge, so a
 * concurrent booking that wins the race comes back as the same kind of Spanish message (ADR-0005).
 *
 * RF_47 and RF_48: the Superusuarios and the space's managers are notified after the commit (SpaceRecipients).
 */
final class CreateReservation
{
    /**
     * @param  array<string, mixed>  $input  space_id, date (Y-m-d), start_time and end_time (H:i), title, attendees,
     *                                       responsible_id, notes
     */
    public function handle(User $actor, array $input): Reservation
    {
        Gate::forUser($actor)->authorize('create', Reservation::class);

        $data = Validator::make($input, self::rules(), [
            'end_time.after' => __('reservations.validation.end_after_start'),
        ], __('reservations.fields'))->validate();

        $space = Space::query()->with(['spaceType', 'status'])->findOrFail($data['space_id']);

        $startsAt = Carbon::parse("{$data['date']} {$data['start_time']}");
        $endsAt = Carbon::parse("{$data['date']} {$data['end_time']}");

        self::ensureBookable($space, $startsAt, $endsAt, (int) $data['attendees']);

        try {
            $reservation = DB::transaction(fn (): Reservation => Reservation::query()->create([
                'space_id' => $space->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'title' => trim($data['title']),
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'attendees' => (int) $data['attendees'],
                'responsible_id' => (int) $data['responsible_id'],
                'created_by' => $actor->id,
            ]));
        } catch (QueryException $e) {
            throw ConstraintViolation::toValidationException($e, field: self::fieldOf($e));
        }

        Notification::send(SpaceRecipients::of($space, $actor), ReservationCreated::of($space, [$reservation], $actor));

        return $reservation;
    }

    /**
     * The business rules of the RF_46 diagram, in its order, each as a message under the field to correct. A
     * modification (RF_49) checks the same rules and passes its reservation as $ignoreId, so it never overlaps itself.
     *
     * @throws ValidationException
     */
    public static function ensureBookable(Space $space, Carbon $startsAt, Carbon $endsAt, int $attendees, ?int $ignoreId = null): void
    {
        if (! $space->isReservable()) {
            throw ValidationException::withMessages(['space_id' => __('reservations.create.errors.not_reservable')]);
        }

        if ($startsAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['start_time' => __('reservations.create.errors.in_past')]);
        }

        if (! self::onSlot($startsAt) || ! self::onSlot($endsAt)) {
            throw ValidationException::withMessages([
                'start_time' => __('reservations.create.errors.off_slot', ['minutes' => self::slotMinutes()]),
            ]);
        }

        if (! self::withinAllowedHours($startsAt, $endsAt)) {
            throw ValidationException::withMessages(['start_time' => self::allowedHoursMessage()]);
        }

        if ($attendees > $space->capacity) {
            throw ValidationException::withMessages([
                'attendees' => __('reservations.create.errors.over_capacity', ['capacity' => $space->capacity]),
            ]);
        }

        if (Reservation::query()->overlapping($space->id, $startsAt, $endsAt, $ignoreId)->exists()) {
            throw ValidationException::withMessages(['start_time' => __('reservations.create.errors.overlap')]);
        }
    }

    /** Inside the allowed days, starting at or after opening and ending at or before closing (same date). */
    public static function withinAllowedHours(Carbon $startsAt, Carbon $endsAt): bool
    {
        /** @var array{days: list<int>, opens: string, closes: string} $hours */
        $hours = config('reservations.allowed_hours');

        return in_array($startsAt->isoWeekday(), $hours['days'], true)
            && $startsAt->isSameDay($endsAt)
            && $startsAt->format('H:i') >= $hours['opens']
            && $endsAt->format('H:i') <= $hours['closes'];
    }

    /** Starts or ends on the grid of config/reservations.php (10:00, 10:15… with 15-minute slots). */
    public static function onSlot(Carbon $time): bool
    {
        return $time->second === 0 && $time->minute % self::slotMinutes() === 0;
    }

    /**
     * The times a reservation may start: every slot from opening to the last slot before closing ("07:00" … "21:45").
     *
     * @return list<string>
     */
    public static function startTimes(): array
    {
        return array_slice(self::slotTimes(), 0, -1);
    }

    /**
     * The times a reservation may end: every slot after opening up to closing, after $startTime when given.
     *
     * @return list<string>
     */
    public static function endTimes(?string $startTime = null): array
    {
        return array_values(array_filter(
            array_slice(self::slotTimes(), 1),
            fn (string $time): bool => blank($startTime) || $time > $startTime,
        ));
    }

    /**
     * Every slot from opening to closing, both included.
     *
     * @return list<string>
     */
    private static function slotTimes(): array
    {
        /** @var array{days: list<int>, opens: string, closes: string} $hours */
        $hours = config('reservations.allowed_hours');
        $times = [];

        for ($time = Carbon::parse($hours['opens']); $time->format('H:i') <= $hours['closes'] && $time->isToday(); $time->addMinutes(self::slotMinutes())) {
            $times[] = $time->format('H:i');
        }

        return $times;
    }

    private static function slotMinutes(): int
    {
        return (int) config('reservations.slot_minutes');
    }

    /** "Solo se puede reservar de lunes a sábado, de 07:00 a 22:00." */
    public static function allowedHoursMessage(): string
    {
        /** @var array{days: list<int>, opens: string, closes: string} $hours */
        $hours = config('reservations.allowed_hours');
        $days = array_map(fn (int $day): string => __("reservations.weekdays.{$day}"), $hours['days']);

        return __('reservations.create.errors.outside_hours', [
            'days' => count($days) > 1
                ? implode(', ', array_slice($days, 0, -1)).' '.__('reservations.weekdays.and').' '.end($days)
                : implode('', $days),
            'opens' => $hours['opens'],
            'closes' => $hours['closes'],
        ]);
    }

    /** The form field a violated reservations constraint belongs to, so its message shows under that field. */
    public static function fieldOf(QueryException $exception): string
    {
        return match (ConstraintViolation::ruleName($exception)) {
            'reservations_no_overlap', 'reservations_period_check' => 'start_time',
            'reservations_attendees_check' => 'attendees',
            'reservations_title_check' => 'title',
            default => 'form',
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'space_id' => ['required', 'integer', Rule::exists('spaces', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'title' => ['required', 'string', 'max:150'],
            'attendees' => ['required', 'integer', 'min:1'],
            'responsible_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
