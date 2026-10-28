<?php

namespace App\Queries\Users;

use App\Models\Reporting\SpaceManagerRow;
use App\Models\Reporting\SpaceRow;
use App\Models\Reporting\UserRow;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who is in charge of which space, as lists and reports see it (reporting.space_managers, reporting.spaces): the
 * managers of a space (RF_96) and the spaces of an Administrador (RF_97) · @Rubiocai. Deleted spaces are not listed. The assignments themselves are written by
 * App\Actions\Spaces\AssignSpaceManager and RemoveSpaceManager (RF_40, RF_43).
 *
 *     SpaceManagers::ofSpace($space->id);     // its managers, by name
 *     SpaceManagers::ofManager($user->id);    // their spaces, by code, with the space type
 */
final class SpaceManagers
{
    /**
     * Every non-deleted space a manager can be looked up for, any type (ADR-0009), by code.
     *
     * @return Collection<int, SpaceRow>
     */
    public static function spaces(): Collection
    {
        return SpaceRow::query()->orderBy('code')->get(['id', 'code', 'name', 'space_type']);
    }

    /**
     * The managers of one space, by name.
     *
     * @return Collection<int, SpaceManagerRow>
     */
    public static function ofSpace(int $spaceId): Collection
    {
        return SpaceManagerRow::query()
            ->where('space_id', $spaceId)
            ->orderBy('manager_name')
            ->orderBy('user_id')
            ->get();
    }

    /**
     * Every holder of the Administrador role, active or deactivated, by name: the same "administradores" as the users
     * list's user type filter (RF_92), so the word means one thing everywhere.
     *
     * @return Collection<int, UserRow>
     */
    public static function administrators(): Collection
    {
        return UserDirectory::query(['user_type' => ['values' => ['administrators']]])
            ->get(['id', 'name', 'email', 'account_status']);
    }

    /**
     * The non-deleted spaces one user is in charge of, by code, each with its space type.
     *
     * @return Collection<int, SpaceManagerRow>
     */
    public static function ofManager(int $userId): Collection
    {
        return SpaceManagerRow::query()
            ->join('reporting.spaces as spaces', 'spaces.id', '=', 'space_managers.space_id')
            ->where('space_managers.user_id', $userId)
            ->orderBy('space_managers.space_code')
            ->get(['space_managers.*', 'spaces.space_type']);
    }
}
