<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * RF_88 criterion 4 and RF_98 · Give a deactivated user their access back: it clears deactivated_at/deactivated_by
 * and writes the `reactivated` audit entry, in one transaction. The user signs in again with their own password.
 */
final class ReactivateUser
{
    public function handle(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('deactivate', $user);

        if ($user->isActive()) {
            throw ValidationException::withMessages(['account' => __('users.account.errors.already_active', ['user' => $user->name])]);
        }

        DB::transaction(function () use ($actor, $user): void {
            $user->forceFill(['deactivated_at' => null, 'deactivated_by' => null])->save();

            Audit::record(AuditAction::Reactivated, $user, actor: $actor);
        });

        return $user;
    }
}
