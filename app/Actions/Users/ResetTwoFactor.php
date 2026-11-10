<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Clear a user's two-factor authentication when they lost their phone and their recovery codes, so they are not locked
 * out for good: in one transaction it removes the secret, the recovery codes and the confirmation, ends the user's
 * sessions and writes an audit entry (`updated`, two_factor: enabled → reset; the secret never reaches the log). The
 * user signs in with their password; one who must use two-factor authentication sets it up again before anything else
 * (RequireTwoFactor). The Superusuario confirms the person's identity out of band first: this removes a second factor.
 *
 * Who: UserPolicy::resetTwoFactor (users.update, Superusuario). Nobody resets their own here: the security page
 * disables it after confirming the password.
 */
final class ResetTwoFactor
{
    public function handle(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('resetTwoFactor', $user);

        if ($actor->is($user)) {
            throw ValidationException::withMessages(['two_factor' => __('users.two_factor.errors.self')]);
        }

        DB::transaction(function () use ($actor, $user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->refresh();

            if ($user->two_factor_secret === null) {
                throw ValidationException::withMessages(['two_factor' => __('users.two_factor.errors.not_enabled', ['user' => $user->name])]);
            }

            // Not an "updated" entry of the model: these columns are secrets and stay out of the audit values.
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->saveQuietly();

            $user->endSessions();

            Audit::record(AuditAction::Updated, $user, old: ['two_factor' => 'enabled'], new: ['two_factor' => 'reset'], actor: $actor);
        });

        return $user;
    }
}
