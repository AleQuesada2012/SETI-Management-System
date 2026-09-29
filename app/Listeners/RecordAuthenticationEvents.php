<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

/**
 * Audits authentication (Q33, RF_00_1): login, logout, failed attempts (with the attempted email,
 * for brute-force detection) and password resets. Passwords never reach the log.
 *
 * Registered by Laravel's event discovery: each handle*() method listens to the event it type-hints.
 */
class RecordAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // Not an "updated" audit entry: the login entry below already says it.
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        Audit::record(AuditAction::Login, $event->user, actor: $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            Audit::record(AuditAction::Logout, $event->user, actor: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        Audit::record(
            AuditAction::LoginFailed,
            new: ['email' => is_string($email) ? Str::limit(Str::lower(trim($email)), 255, '') : null],
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            Audit::record(AuditAction::PasswordReset, $event->user, actor: $event->user);
        }
    }
}
