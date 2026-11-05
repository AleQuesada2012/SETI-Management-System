<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * RF_87 · Swap one of a user's roles for another in one transaction (.ai/rules/users.md). It assigns the new role first
 * and then removes the old one, so a user with a single role can swap it without ever holding none, and both steps keep
 * their own rules and audit entries: AssignRole (RF_85, `role_assigned`) and RemoveRole (RF_86, `role_removed`; the
 * last active Superusuario keeps that role; leaving Administrador removes the user's space-manager assignments). If
 * either step fails, nothing changes. The permission cache is cleared at the end, so the new permissions apply at once.
 */
final class ChangeRole
{
    public function __construct(
        private readonly AssignRole $assignRole,
        private readonly RemoveRole $removeRole,
        private readonly PermissionRegistrar $permissions,
    ) {}

    public function handle(User $actor, User $user, Role $from, Role $to): void
    {
        Gate::forUser($actor)->authorize('assignRoles', $user);

        if ($from->is($to)) {
            throw ValidationException::withMessages(['roles' => __('users.change_role.errors.same_role')]);
        }

        try {
            DB::transaction(function () use ($actor, $user, $from, $to): void {
                if (! $user->hasRole($from)) {
                    throw ValidationException::withMessages(['roles' => __('users.remove_role.errors.not_held', ['role' => $from->name])]);
                }

                $this->assignRole->handle($actor, $user, $to);
                $user->unsetRelation('roles');
                $this->removeRole->handle($actor, $user, $from);
            });
        } catch (ValidationException $exception) {
            // AssignRole reports under `roleId` and RemoveRole under `roles`: the change form shows one field.
            throw ValidationException::withMessages(['roles' => collect($exception->errors())->flatten()->all()]);
        } finally {
            $this->permissions->forgetCachedPermissions();
        }
    }
}
