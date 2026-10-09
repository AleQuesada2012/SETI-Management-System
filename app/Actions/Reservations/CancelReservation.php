<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Notifications\Reservations\ReservationCancelled;
use App\Notifications\SpaceRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * RF_50 · "Eliminar reserva", following the RF_50 diagram: the reservation is cancelled, never deleted (ADR-0005), so
 * it frees the space's block and stays in the history with who cancelled it, when and why. Only reservations that
 * have not started can be cancelled (Agent-DB.md §9). The row is locked and re-read inside the transaction, so two
 * concurrent cancellations, or a modification (RF_49) made after the user opened the page, are refused with a Spanish
 * message instead of being applied twice or blindly ($readVersion, optimistic locking). The `updated` audit entry is
 * written by Auditable; the Superusuarios and the space's managers are notified after the commit.
 */
final class CancelReservation
{
    /**
     * @param  int  $readVersion  the reservation's version when the user opened it
     */
    public function handle(User $actor, Reservation $reservation, int $readVersion, ?string $reason = null): Reservation
    {
        Gate::forUser($actor)->authorize('cancel', $reservation);

        $data = Validator::make(['cancellation_reason' => $reason], [
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ], [], __('reservations.fields'))->validate();

        $cancelled = DB::transaction(function () use ($actor, $reservation, $readVersion, $data): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            $this->ensureCancellable($locked, $readVersion);

            $locked->forceFill([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => filled($data['cancellation_reason']) ? trim($data['cancellation_reason']) : null,
            ])->save();

            return $locked;
        });

        /** @var Space $space */
        $space = Space::withTrashed()->findOrFail($cancelled->space_id);

        Notification::send(SpaceRecipients::of($space, $actor), ReservationCancelled::of($space, [$cancelled], $actor));

        return $cancelled;
    }

    /**
     * The checks of the RF_50 diagram on the locked row, each with the message the user reads.
     *
     * @throws ValidationException
     */
    private function ensureCancellable(Reservation $reservation, int $readVersion): void
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            throw ValidationException::withMessages(['reservation' => __('reservations.cancel.errors.already_cancelled')]);
        }

        if ($reservation->starts_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['reservation' => __('reservations.cancel.errors.started')]);
        }

        if ($reservation->version !== $readVersion) {
            throw ValidationException::withMessages(['reservation' => __('reservations.cancel.errors.changed')]);
        }
    }
}
