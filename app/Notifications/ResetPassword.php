<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The "¿Olvidaste tu contraseña?" email, sent by the queue worker instead of inside the request: talking to the mail
 * server made the answer slower for a registered email than for an unregistered one, which told a visitor which emails
 * have an account (SECURITY.md). The job carries the reset token, so its payload is encrypted in the `jobs` table.
 */
final class ResetPassword extends BaseResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
}
