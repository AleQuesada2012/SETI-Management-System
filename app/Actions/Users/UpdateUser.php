<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserInvitation;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * RF_93 · Change a user's name and email (the data the Superusuario may edit; roles and account status have their own
 * flows, and only the user sets their password). Validation mirrors RegisterUser and the database still has the last
 * word (users_email_unique, users_email_format_check, users_name_check). The Auditable trait records the change as
 * `updated` with the old and new values.
 *
 * The email is the login, so a new one is not trusted until its owner proves it: an active account gets it marked
 * unverified and receives a verification link at the new address; a pending invitation is sent again to the new address
 * (the first one went to the wrong inbox). Links tied to the old address are deleted.
 */
final class UpdateUser
{
    /**
     * @param  array{name?: mixed, email?: mixed}  $input
     */
    public function handle(User $actor, User $user, array $input): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        /** @var array{name: string, email: string} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:150'],
            // Same shape as users_email_format_check, so the database never rejects what passed here.
            'email' => ['required', 'string', 'max:255', 'email', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', Rule::unique('users', 'email')->ignore($user->id)],
        ], attributes: [
            'name' => Str::lower(__('users.fields.name')),
            'email' => Str::lower(__('users.fields.email')),
        ])->validate();

        $oldEmail = $user->email;
        $emailChanged = $data['email'] !== $oldEmail;
        $invitationPending = $user->password === null;

        try {
            DB::transaction(function () use ($user, $data, $emailChanged, $oldEmail): void {
                $user->name = trim($data['name']);
                $user->email = $data['email'];

                if ($emailChanged) {
                    $user->email_verified_at = null;

                    DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
                    DB::table('user_invitation_tokens')->where('email', $oldEmail)->delete();
                }

                $user->save();
            });
        } catch (QueryException $exception) {
            $field = match (ConstraintViolation::ruleName($exception)) {
                'users_email_unique', 'users_email_format_check', 'users_email_lowercase_check' => 'email',
                'users_name_check' => 'name',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($exception, field: $field);
        }

        if ($emailChanged) {
            DB::afterCommit(function () use ($user, $invitationPending): void {
                if ($invitationPending) {
                    // createToken, not sendResetLink: the broker's resend throttle must not swallow this one.
                    $user->notify(new UserInvitation(Password::broker('invitations')->createToken($user)));
                } else {
                    $user->sendEmailVerificationNotification();
                }
            });
        }

        return $user;
    }
}
