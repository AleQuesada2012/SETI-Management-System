<?php

namespace App\Queries\Users;

use App\Models\Reporting\SpaceManagerRow;
use App\Models\Reporting\SpaceRow;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who is in charge of which space, as lists and reports see it (reporting.space_managers, reporting.spaces): the
 * managers of a space (RF_96) · @Rubiocai. Deleted spaces are not listed. The assignments themselves are written by
 * App\Actions\Spaces\AssignSpaceManager and RemoveSpaceManager (RF_40, RF_43).
 *
 *     SpaceManagers::ofSpace($space->id);   // its managers, by name
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
}
