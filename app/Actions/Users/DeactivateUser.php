<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RF_88 · Revoke a user's access without deleting anything: users are never deleted (Agent-DB.md §5), so their
 * reservations, movements, files and audit trail keep pointing at them. In one transaction it sets
 * deactivated_at/deactivated_by, deletes the user's sessions (database driver) and cycles the remember token, so access
 * ends at once, and writes the `deactivated` audit entry. Login (FortifyServiceProvider) and every request
 * (EnsureUserIsActive) already refuse a deactivated account.
 *
 * Invariants no policy can enforce (the Superusuario passes every policy): nobody deactivates their own account, and the
 * last active Superusuario stays active.
 */
final class DeactivateUser
{
    public function handle(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('deactivate', $user);

        if ($actor->is($user)) {
            throw ValidationException::withMessages(['account' => __('users.account.errors.self')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['account' => __('users.account.errors.already_deactivated', ['user' => $user->name])]);
        }

        DB::transaction(function () use ($actor, $user): void {
            // Lock the active Superusuarios so two deactivations cannot both pass the "last one" check.
            $activeSuperusers = User::role(SystemRole::Superusuario->value)
                ->whereNull('deactivated_at')
                ->lockForUpdate()
                ->pluck('id');

            if ($user->isSuperuser() && $activeSuperusers->count() <= 1) {
                throw ValidationException::withMessages(['account' => __('users.account.errors.last_superuser')]);
            }

            $user->forceFill([
                'deactivated_at' => now(),
                'deactivated_by' => $actor->id,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            Audit::record(AuditAction::Deactivated, $user, actor: $actor);
        });

        return $user;
    }
}
