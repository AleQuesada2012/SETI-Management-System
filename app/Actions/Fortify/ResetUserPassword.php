<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        // A link sent before the account was deactivated (RF_88) no longer changes its password.
        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('auth.deactivated')]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // Following an emailed reset or invitation link proves the user owns the inbox (Q21).
        $user->forceFill([
            'password' => $input['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // Whoever was signed in with the old password, on any device, is signed out.
        $user->endSessions();
    }
}
