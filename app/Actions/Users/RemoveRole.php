<?php

namespace App\Actions\Users;

use App\Actions\Spaces\RemoveSpaceManager;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * RF_86 · Take one role away from a user. A pivot change fires no model event, so the `role_removed` audit entry is
 * written here, in the same transaction; then the permission cache is cleared so the role's permissions stop applying
 * at once. Removing the Administrador role also removes the user's space-manager assignments (Agent-DB.md §6), through
 * RemoveSpaceManager (RF_43) so each one is audited as `manager_removed`.
 *
 * Invariants no policy can enforce (the Superusuario passes every policy): a user always keeps at least one role, and
 * the last active Superusuario keeps that role.
 */
final class RemoveRole
{
    public function __construct(
        private readonly PermissionRegistrar $permissions,
        private readonly RemoveSpaceManager $removeSpaceManager,
    ) {}

    public function handle(User $actor, User $user, Role $role): void
    {
        Gate::forUser($actor)->authorize('assignRoles', $user);

        if (! $user->hasRole($role)) {
            throw ValidationException::withMessages(['roles' => __('users.remove_role.errors.not_held', ['role' => $role->name])]);
        }

        if ($user->roles()->count() <= 1) {
            throw ValidationException::withMessages(['roles' => __('users.remove_role.errors.last_role', ['user' => $user->name])]);
        }

        DB::transaction(function () use ($actor, $user, $role): void {
            if ($role->name === SystemRole::Superusuario->value && $user->isActive()) {
                // Lock the active Superusuarios (PostgreSQL refuses FOR UPDATE with count(*), so lock the rows, then count)
                // so two removals cannot both pass the "last one" check.
                $activeSuperusers = User::role(SystemRole::Superusuario->value)->whereNull('deactivated_at')->lockForUpdate()->pluck('id');

                if ($activeSuperusers->count() <= 1) {
                    throw ValidationException::withMessages(['roles' => __('users.remove_role.errors.last_superuser')]);
                }
            }

            if ($role->name === SystemRole::Administrador->value) {
                $spaceIds = $user->managedSpaces()->pluck('spaces.id')->all();

                if ($spaceIds !== []) {
                    $this->removeSpaceManager->handle($actor, $user, $spaceIds);
                }
            }

            $user->removeRole($role);

            Audit::record(AuditAction::RoleRemoved, $user, old: ['role' => $role->name], actor: $actor);
        });

        $this->permissions->forgetCachedPermissions();
    }
}
