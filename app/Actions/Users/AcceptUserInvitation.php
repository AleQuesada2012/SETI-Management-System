<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class AcceptUserInvitation
{
    public function __construct(private readonly ResetsUserPasswords $resetPassword) {}

    /** @param array<string, mixed> $input */
    public function handle(array $input): void
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        $validated = Validator::make($input, [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'password_confirmation' => ['required', 'string'],
        ])->validate();

        $status = DB::transaction(fn () => Password::broker('invitations')->reset(
            [
                ...$validated,
                'pending_invitation' => fn (Builder $query) => $query
                    ->whereNull('password')
                    ->whereNull('deactivated_at')
                    ->lockForUpdate(),
            ],
            function (User $user) use ($validated): void {
                $user->setRememberToken(Str::random(60));
                $this->resetPassword->reset($user, $validated);
                Audit::record(AuditAction::PasswordSet, $user, actor: $user);
            },
        ));

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
    }
}
