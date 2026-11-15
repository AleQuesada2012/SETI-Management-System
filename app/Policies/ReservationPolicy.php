<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;

/**
 * Reservations (RF_45–RF_52). Anyone with reservations.manage_own changes the reservations they made or are
 * responsible for; reservations.manage_any is space-scoped (★): an Administrador changes any reservation of the
 * spaces they manage. Whether a space is reservable, and overlaps, are business rules checked by the Actions.
 */
class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::ReservationsView);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->can(P::ReservationsView);
    }

    /** RF_46. */
    public function create(User $user, ?Space $space = null): bool
    {
        return $user->can(P::ReservationsCreate);
    }

    /** RF_49. */
    public function update(User $user, Reservation $reservation): bool
    {
        return ($this->isOwn($user, $reservation) && $user->can(P::ReservationsManageOwn))
            || $user->canInSpace(P::ReservationsManageAny, $reservation->space_id);
    }

    /** RF_50 ("Cancelar reserva" in the interface). */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    private function isOwn(User $user, Reservation $reservation): bool
    {
        return $reservation->responsible_id === $user->id || $reservation->created_by === $user->id;
    }
}
