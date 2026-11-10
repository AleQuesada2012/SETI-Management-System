<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer to "¿Olvidaste tu contraseña?" when no link was sent: the email is not registered, or its last link is
 * too recent. It is the same answer as when the link was sent, so the form never tells a visitor which emails have an
 * account (RF_00_1). Bound in FortifyServiceProvider.
 */
final class PasswordResetLinkRequested implements FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request): Response
    {
        return app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => Password::RESET_LINK_SENT])
            ->toResponse($request);
    }
}
