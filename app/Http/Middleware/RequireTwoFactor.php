<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Superusuario and the Administradores sign in with two-factor authentication (User::mustUseTwoFactor). Until one of
 * them has set it up, every page sends them to their security page, where they do; they can still confirm their
 * password, verify their email and sign out. Turned off with TWO_FACTOR_REQUIRED=false (local demo data only).
 *
 * Livewire's own requests pass: a component only answers with a snapshot from a page, and the pages are closed here. A
 * user who gets one of these roles while signed in is signed out by AssignRole, so they hold no open page either.
 */
class RequireTwoFactor
{
    /** Route names a user who still has to set up two-factor authentication may use. */
    public const array OPEN_ROUTES = [
        'security.edit', 'password.confirm', 'password.confirm.store', 'password.confirmation', 'two-factor.*',
        'verification.*', 'logout', '*livewire*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! config('auth.two_factor_required')
            || ! $user->mustSetUpTwoFactor()
            || $request->routeIs(...self::OPEN_ROUTES)
            || $request->is('livewire*', 'flux/*')) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, __('security.two_factor.required'));

        return redirect()->route('security.edit')->with('status', __('security.two_factor.required'));
    }
}
