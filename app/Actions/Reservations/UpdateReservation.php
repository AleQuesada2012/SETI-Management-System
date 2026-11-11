<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Notifications\Reservations\ReservationMovedIn;
use App\Notifications\Reservations\ReservationMovedOut;
use App\Notifications\Reservations\ReservationUpdated;
use App\Notifications\SpaceRecipients;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * RF_49 · Modify a reservation, following the RF_49 diagram: its space, date, block, attendees, reason, responsible and
 * notes can change, with an optional reason for the change. Only reservations that have not started can be modified
 * (Agent-DB.md §9), and the new values follow the same rules as a new booking (CreateReservation::ensureBookable),
 * ignoring the reservation itself when looking for overlaps; reservations_no_overlap stays the final judge (ADR-0005).
 *
 * Optimistic locking: the row is locked and re-read inside the transaction and its version compared with the one the
 * user opened ($readVersion), so a change made meanwhile is refused instead of overwritten; the save bumps `version`,
 * which is the "modificada" label. The `updated` audit entry, with every changed field and the reason, is written by
 * Auditable. After the commit the Superusuarios and the space's managers are notified; a move to another space
 * notifies those of the space it left (ReservationMovedOut) and those of the new one (ReservationMovedIn).
 */
final class UpdateReservation
{
    /**
     * @param  int  $readVersion  the reservation's version when the user opened it
     * @param  array<string, mixed>  $input  the fields of CreateReservation plus modification_reason
     */
    public function handle(User $actor, Reservation $reservation, int $readVersion, array $input): Reservation
    {
        Gate::forUser($actor)->authorize('update', $reservation);

        $data = Validator::make($input, [
            ...CreateReservation::rules(),
            'modification_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'end_time.after' => __('reservations.validation.end_after_start'),
        ], __('reservations.fields'))->validate();

        $space = Space::query()->with(['spaceType', 'status'])->findOrFail($data['space_id']);

        $startsAt = Carbon::parse("{$data['date']} {$data['start_time']}");
        $endsAt = Carbon::parse("{$data['date']} {$data['end_time']}");

        try {
            [$updated, $before] = DB::transaction(function () use ($reservation, $readVersion, $data, $space, $startsAt, $endsAt): array {
                $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

                $this->ensureModifiable($locked, $readVersion);

                CreateReservation::holdReservable($space);

                CreateReservation::ensureBookable($space, $startsAt, $endsAt, (int) $data['attendees'], $locked->id);

                $before = clone $locked;

                $locked->fill([
                    'space_id' => $space->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'title' => trim($data['title']),
                    'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                    'attendees' => (int) $data['attendees'],
                    'responsible_id' => (int) $data['responsible_id'],
                ]);

                if (! $locked->isDirty()) {
                    throw ValidationException::withMessages(['form' => __('reservations.edit.errors.unchanged')]);
                }

                $locked->forceFill([
                    'version' => $locked->version + 1,
                    'modification_reason' => filled($data['modification_reason'] ?? null) ? trim($data['modification_reason']) : null,
                ])->save();

                return [$locked, $before];
            });
        } catch (QueryException $e) {
            throw ConstraintViolation::toValidationException($e, field: CreateReservation::fieldOf($e));
        }

        $this->notify($actor, $updated, $before, $space);

        return $updated;
    }

    /**
     * The checks of the RF_49 diagram on the locked row, each with the message the user reads.
     *
     * @throws ValidationException
     */
    private function ensureModifiable(Reservation $reservation, int $readVersion): void
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            throw ValidationException::withMessages(['form' => __('reservations.edit.errors.cancelled')]);
        }

        if ($reservation->starts_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['form' => __('reservations.edit.errors.started')]);
        }

        if ($reservation->version !== $readVersion) {
            throw ValidationException::withMessages(['form' => __('reservations.edit.errors.changed')]);
        }
    }

    /** One notification per space involved: the space it stays in, or the one it left and the one it moved to. */
    private function notify(User $actor, Reservation $updated, Reservation $before, Space $space): void
    {
        /** @var Space $previousSpace */
        $previousSpace = Space::withTrashed()->findOrFail($before->space_id);
        $reason = $updated->modification_reason;

        if ($previousSpace->is($space)) {
            Notification::send(
                SpaceRecipients::of($space, $actor),
                ReservationUpdated::of($space, [$updated], $actor)->withPrevious($previousSpace, $before->starts_at, $before->ends_at, $reason),
            );

            return;
        }

        Notification::send(
            SpaceRecipients::of($previousSpace, $actor),
            ReservationMovedOut::of($space, [$updated], $actor)->withPrevious($previousSpace, $before->starts_at, $before->ends_at, $reason),
        );
        Notification::send(
            SpaceRecipients::of($space, $actor),
            ReservationMovedIn::of($space, [$updated], $actor)->withPrevious($previousSpace, $before->starts_at, $before->ends_at, $reason),
        );
    }
}
