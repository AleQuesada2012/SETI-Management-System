<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * The first Superusuario of a fresh database (`php artisan users:create-first-superuser`): with no self-registration
 * and no Superusuario to register anyone (RF_91), there is no other way in. Once one exists it refuses, so it is never
 * a second door. Like RegisterUser it creates the user without a password, gives the role (audited from the CLI) and
 * emails the invitation; at first sign-in RequireTwoFactor makes them set up two-factor authentication.
 */
final class CreateFirstSuperuser
{
    public function __construct(private readonly PermissionRegistrar $permissions) {}

    public function handle(string $name, string $email): User
    {
        /** @var array{name: string, email: string} $data */
        $data = Validator::make(['name' => trim($name), 'email' => Str::lower(trim($email))], [
            'name' => ['required', 'string', 'max:150'],
            // Same shape as users_email_format_check, as in RegisterUser.
            'email' => ['required', 'string', 'max:255', 'email', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', Rule::unique('users', 'email')],
        ])->validate();

        $user = DB::transaction(function () use ($data): User {
            // Lock the role so two runs at once cannot both find no Superusuario.
            $role = Role::query()->where('name', SystemRole::Superusuario->value)->lockForUpdate()->firstOrFail();

            if (User::role($role)->exists()) {
                throw ValidationException::withMessages(['email' => __('users.first_superuser.exists')]);
            }

            $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => null]);
            $user->assignRole($role);

            Audit::record(AuditAction::RoleAssigned, $user, new: ['role' => $role->name]);

            return $user;
        });

        $this->permissions->forgetCachedPermissions();

        Password::broker('invitations')->sendResetLink(
            ['email' => $user->email],
            fn (User $recipient, string $token) => $recipient->notify(new UserInvitation($token)),
        );

        return $user;
    }
}
