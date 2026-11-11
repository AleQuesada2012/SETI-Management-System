<?php

namespace App\Http\Middleware;

use App\Http\Responses\SignedOut;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account loses access immediately (RF_88), even with a session still open.
 * Deactivation also deletes the user's sessions; this is the defence in depth.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->deactivated_at !== null) {
            return SignedOut::because($request, 'deactivated');
        }

        return $next($request);
    }
}
