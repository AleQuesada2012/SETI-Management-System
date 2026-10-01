<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Support\Audit\Audit;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

/**
 * RF_91 · Register a user from name, email and role(s), with no password (Q21): in one transaction it creates the
 * user (audited as `created` by the Auditable trait) and gives the roles (one `role_assigned` entry each); after
 * commit it emails the invitation, a password-broker link that sets the password and email_verified_at
 * (ResetUserPassword). An invitee whose link expired asks for a new one with "¿Olvidaste tu contraseña?".
 */
final class RegisterUser
{
    public function __construct(private readonly PermissionRegistrar $permissions) {}

    /**
     * @param  array{name?: mixed, email?: mixed, roles?: mixed}  $input
     */
    public function handle(User $actor, array $input): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        // Emails are stored lowercase (users_email_lowercase_check), so the unique check compares lowercase too.
        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        /** @var array{name: string, email: string, roles: list<int>} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            // Same shape as users_email_format_check, so the database never rejects what passed here.
            'email' => ['required', 'string', 'max:255', 'email', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', Rule::unique('users', 'email')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'distinct', Rule::exists('roles', 'id')],
        ], attributes: [
            'name' => Str::lower(__('users.fields.name')),
            'email' => Str::lower(__('users.fields.email')),
            'roles' => Str::lower(__('users.fields.roles')),
            'roles.*' => Str::lower(__('users.fields.roles')),
        ])->validate();

        try {
            $user = DB::transaction(function () use ($actor, $data): User {
                $user = User::query()->create([
                    'name' => trim($data['name']),
                    'email' => $data['email'],
                    'password' => null,
                ]);

                Role::query()->whereKey($data['roles'])->orderBy('name')->each(function (Role $role) use ($actor, $user): void {
                    $user->assignRole($role);

                    Audit::record(AuditAction::RoleAssigned, $user, new: ['role' => $role->name], actor: $actor);
                });

                return $user;
            });
        } catch (QueryException $exception) {
            $field = match (ConstraintViolation::ruleName($exception)) {
                'users_email_unique', 'users_email_format_check', 'users_email_lowercase_check' => 'email',
                'users_name_check' => 'name',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($exception, field: $field);
        }

        $this->permissions->forgetCachedPermissions();

        DB::afterCommit(fn () => Password::broker('invitations')->sendResetLink(
            ['email' => $user->email],
            fn (User $recipient, string $token) => $recipient->notify(new UserInvitation($token)),
        ));

        return $user;
    }
}
