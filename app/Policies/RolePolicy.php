<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Role;
use App\Models\User;

/**
 * Roles (RF_100). System roles (roles.is_system) can never be renamed or deleted; the Actions enforce that.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(P::RolesView);
    }

    public function create(User $user): bool
    {
        return $user->can(P::RolesManage);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(P::RolesManage);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(P::RolesManage);
    }
}
