<?php

namespace App\Notifications;

use App\Enums\SystemRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who hears about something that happened in a space (Agent-DB.md §10, Q34): the active Superusuarios and the space's
 * managers, never the person who did it. A space without managers notifies only the Superusuarios. Reservations
 * (RF_47–RF_50) and damage or loss reports use the same rule.
 */
final class SpaceRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function of(Space $space, User $actor): Collection
    {
        return User::query()
            ->whereNull('deactivated_at')
            ->whereKeyNot($actor->id)
            ->where(fn (Builder $query) => $query
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', SystemRole::Superusuario->value))
                ->orWhereHas('managedSpaces', fn (Builder $spaces) => $spaces->whereKey($space->id)))
            ->get();
    }
}
