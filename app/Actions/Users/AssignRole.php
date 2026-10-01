<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * RF_85 · Give a user one more role (ADR-0008: a user's permissions are the union of their roles). A pivot change
 * fires no model event, so the `role_assigned` audit entry is written here, in the same transaction.
 */
final class AssignRole
{
    public function __construct(private readonly PermissionRegistrar $permissions) {}

    public function handle(User $actor, User $user, Role $role): void
    {
        Gate::forUser($actor)->authorize('assignRoles', $user);

        if ($user->hasRole($role)) {
            throw ValidationException::withMessages([
                'roleId' => __('users.assign_role.already_held', ['role' => $role->name]),
            ]);
        }

        DB::transaction(function () use ($actor, $user, $role): void {
            $user->assignRole($role);

            Audit::record(AuditAction::RoleAssigned, $user, new: ['role' => $role->name], actor: $actor);
        });

        $this->permissions->forgetCachedPermissions();
    }
}
