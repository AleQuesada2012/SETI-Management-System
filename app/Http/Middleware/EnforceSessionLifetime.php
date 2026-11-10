<?php

namespace App\Http\Middleware;

use App\Http\Responses\SignedOut;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A session ends `session.absolute_lifetime` minutes after sign-in (9 hours: a working day), however active it is.
 * `session.lifetime` only ends idle sessions, so without this a session in use, or one kept alive by a stolen cookie,
 * would never end. The sign-in time is noted on the first request after signing in; signing out clears the session.
 */
class EnforceSessionLifetime
{
    public const string SIGNED_IN_AT = 'auth.signed_in_at';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || ! $request->hasSession()) {
            return $next($request);
        }

        $signedInAt = $request->session()->get(self::SIGNED_IN_AT);

        if (! is_int($signedInAt)) {
            $request->session()->put(self::SIGNED_IN_AT, now()->getTimestamp());

            return $next($request);
        }

        if (now()->getTimestamp() - $signedInAt < 60 * (int) config('session.absolute_lifetime')) {
            return $next($request);
        }

        return SignedOut::because($request, 'expired');
    }
}
