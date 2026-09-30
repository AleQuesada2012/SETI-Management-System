<?php

namespace App\Actions\Spaces;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * RF_44 · Set a space's operational status (Disponible, En mantenimiento, Inhabilitado); RF_58 is its duplicate.
 * Space-scoped (★), like any change to a space. A status that does not allow reservations leaves the confirmed
 * reservations that have not ended in place, so the user must see them first (Agent-DB.md §6): the change is
 * refused until the caller confirms it knows. Cancelling them is a separate reservations operation that notifies
 * as usual. "Ocupado" is never a status: it is derived from reservations. The `updated` audit entry is written by
 * Auditable.
 */
final class ChangeSpaceStatus
{
    public function handle(User $actor, Space $space, SpaceStatus $status, bool $confirmedAffectedReservations = false): Space
    {
        Gate::forUser($actor)->authorize('update', $space);

        $affected = self::affectedReservations($space, $status);

        if ($affected > 0 && ! $confirmedAffectedReservations) {
            throw ValidationException::withMessages([
                'status' => trans_choice('spaces.status.affected', $affected, ['count' => $affected]),
            ]);
        }

        return DB::transaction(function () use ($space, $status): Space {
            $space->status()->associate($status)->save();

            return $space;
        });
    }

    /** Confirmed reservations of the space that have not ended and that the new status would no longer allow. */
    public static function affectedReservations(Space $space, SpaceStatus $status): int
    {
        if ($status->allows_reservations) {
            return 0;
        }

        return Reservation::query()->confirmed()
            ->where('space_id', $space->id)
            ->where('ends_at', '>', now())
            ->count();
    }
}
