<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\User;

/**
 * User accounts (RF_85–RF_101). Invariants no one may break, not even the Superusuario (the last active
 * Superusuario, deactivating yourself…), are enforced by the Actions in app/Actions/Users, not here.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::UsersView);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(P::UsersView);
    }

    /** RF_91. */
    public function create(User $user): bool
    {
        return $user->can(P::UsersCreate);
    }

    /** RF_93. */
    public function update(User $user, User $model): bool
    {
        return $user->can(P::UsersUpdate);
    }

    /** RF_88, RF_98. */
    public function deactivate(User $user, User $model): bool
    {
        return $user->can(P::UsersDeactivate);
    }

    /** Clear a user's two-factor authentication after they lost their device (ResetTwoFactor). */
    public function resetTwoFactor(User $user, User $model): bool
    {
        return $user->can(P::UsersUpdate);
    }

    /** RF_85–RF_87. */
    public function assignRoles(User $user, User $model): bool
    {
        return $user->can(P::RolesAssign);
    }
}
