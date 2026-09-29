<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Space;
use App\Models\User;

/**
 * Spaces (RF_32–RF_44, RF_53–RF_59). Creating is not scoped (the creator becomes its first manager); changing a
 * space is space-scoped (★): an Administrador changes only the spaces they manage.
 */
class SpacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::SpacesView);
    }

    public function view(User $user, Space $space): bool
    {
        return $user->can(P::SpacesView);
    }

    /** RF_32. */
    public function create(User $user): bool
    {
        return $user->can(P::SpacesCreate);
    }

    /** RF_33, RF_36, RF_44, RF_58. */
    public function update(User $user, Space $space): bool
    {
        return $user->canInSpace(P::SpacesUpdate, $space);
    }

    /** RF_34 (soft delete; the Action refuses while it holds assets or upcoming reservations). */
    public function delete(User $user, Space $space): bool
    {
        return $user->canInSpace(P::SpacesDelete, $space);
    }

    /** RF_41. */
    public function managePhotos(User $user, Space $space): bool
    {
        return $user->canInSpace(P::SpacesManagePhotos, $space);
    }

    /** RF_40, RF_43: only the Superusuario holds spaces.assign_managers. */
    public function assignManagers(User $user, Space $space): bool
    {
        return $user->can(P::SpacesAssignManagers);
    }
}
