<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Support\Audit\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person with an account (CONTEXT.md "User"). Login = email + password. Users are never deleted:
 * deactivated_at revokes access and keeps their history (RF_88, Q3).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password NULL until the invitation is accepted (Q21)
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deactivated_at
 * @property int|null $deactivated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'search_text'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /** spatie/laravel-permission guard for roles and permissions. */
    protected string $guard_name = 'web';

    /** @var list<int>|null */
    private ?array $managedSpaceIds = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /** Emails are stored lowercase (users_email_lowercase_check, Q32). */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower(trim($value)));
    }

    /**
     * Spaces this user manages as an Administrador (space_managers, ADR-0009).
     *
     * @return BelongsToMany<Space, $this>
     */
    public function managedSpaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'space_managers')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    /**
     * Assets this user is the custodian of (RF_23, RF_30).
     *
     * @return HasMany<Asset, $this>
     */
    public function custodianOf(): HasMany
    {
        return $this->hasMany(Asset::class, 'responsible_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function isSuperuser(): bool
    {
        return $this->hasRole(SystemRole::Superusuario->value);
    }

    /** The ★ rule: an Administrador acts only on the spaces they manage (Agent-DB.md §5, §6). */
    public function managesSpace(Space|int|null $space): bool
    {
        if ($space === null) {
            return false;
        }

        $this->managedSpaceIds ??= $this->managedSpaces()->pluck('spaces.id')->map(fn ($id): int => (int) $id)->all();

        return in_array($space instanceof Space ? $space->id : $space, $this->managedSpaceIds, true);
    }

    public function managesAnySpace(): bool
    {
        $this->managedSpaceIds ??= $this->managedSpaces()->pluck('spaces.id')->map(fn ($id): int => (int) $id)->all();

        return $this->managedSpaceIds !== [];
    }

    /**
     * The ★ rule for policies: a space-scoped permission (assets.update, spaces.update, reservations.manage_any…,
     * the ✓★ cells of Agent-DB.md §5) lets its holder act only on the spaces they manage and on the assets and
     * reservations in them. The Superusuario is never scoped.
     */
    public function canInSpace(PermissionName $permission, Space|int|null $space): bool
    {
        return $this->isSuperuser() || ($this->can($permission) && $this->managesSpace($space));
    }

    /** Forget the memoized managed spaces (after assigning or removing a space manager). */
    public function refreshManagedSpaces(): void
    {
        $this->managedSpaceIds = null;
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function auditLabel(): string
    {
        return "{$this->name} <{$this->email}>";
    }

    /** @return list<string> */
    public function auditExclude(): array
    {
        // Deactivation and reactivation write their own `deactivated` / `reactivated` entries (RF_88, RF_98).
        return ['last_login_at', 'email_verified_at', 'deactivated_at', 'deactivated_by'];
    }
}
