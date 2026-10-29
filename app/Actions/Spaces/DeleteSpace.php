<?php

namespace App\Actions\Spaces;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * RF_34 · Delete a space that holds nothing. It is a soft delete (Agent-DB.md §6, Q3): refused while the space holds
 * in-service or in-maintenance assets, or has confirmed reservations that have not ended, with the reason and the
 * counts. Past reservations and movements keep pointing at it, and its code and name become free for reuse.
 *
 * Space-scoped (★, SpacePolicy::delete). The space row is locked so an asset or reservation cannot sneak in between the
 * check and the delete. The `deleted` audit entry is written by the Auditable space.
 *
 * @throws ModelNotFoundException when the space no longer exists (deleted meanwhile, e.g. from another tab)
 */
final class DeleteSpace
{
    public function handle(User $actor, Space $space): Space
    {
        Gate::forUser($actor)->authorize('delete', $space);

        return DB::transaction(function () use ($actor, $space): Space {
            $current = Space::query()->whereKey($space->id)->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException)->setModel(Space::class, [$space->id]);

            Gate::forUser($actor)->authorize('delete', $current);

            $assets = self::assetsHeld($current);
            $reservations = self::activeReservations($current);

            $reasons = array_filter([
                $assets > 0 ? trans_choice('spaces.delete.has_assets', $assets, ['count' => $assets]) : null,
                $reservations > 0 ? trans_choice('spaces.delete.has_reservations', $reservations, ['count' => $reservations]) : null,
            ]);

            if ($reasons !== []) {
                throw ValidationException::withMessages(['space' => [__('spaces.delete.refused'), ...array_values($reasons)]]);
            }

            $current->delete();

            return $current;
        });
    }

    /** Assets in the space that are in service or in maintenance. */
    public static function assetsHeld(Space $space): int
    {
        return Asset::query()
            ->where('current_space_id', $space->id)
            ->whereIn('status', [AssetStatus::InService, AssetStatus::InMaintenance])
            ->count();
    }

    /** Confirmed reservations of the space that have not ended. */
    public static function activeReservations(Space $space): int
    {
        return Reservation::query()->confirmed()
            ->where('space_id', $space->id)
            ->where('ends_at', '>', now())
            ->count();
    }
}
