<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * "¿Olvidaste tu contraseña?" accepts 5 requests per minute from one IP address. The password broker already allows
 * one link per email per minute; without this, one address could still ask for links for every account. Fortify
 * declares that route itself, so the limit is applied here by route name instead of in a routes file.
 */
class ThrottlePasswordResetLinks
{
    public const int MAX_PER_MINUTE = 5;

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('password.email')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, self::MAX_PER_MINUTE, 1, 'password-reset-link');
    }
}
