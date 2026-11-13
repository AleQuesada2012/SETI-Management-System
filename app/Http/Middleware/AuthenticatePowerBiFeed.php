<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Basic authentication of the Power BI feed (RF_73): one machine credential from `.env`, entered once in the
 * dataset's settings in Power BI Service. Without POWERBI_FEED_PASSWORD the feed does not exist (404).
 */
class AuthenticatePowerBiFeed
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('reports.powerbi_feed.username');
        $password = (string) config('reports.powerbi_feed.password');

        abort_if($password === '', 404);

        $valid = hash_equals($username, (string) $request->getUser())
            & hash_equals($password, (string) $request->getPassword());

        if (! $valid) {
            return response('', 401, ['WWW-Authenticate' => 'Basic realm="SETI Power BI", charset="UTF-8"']);
        }

        return $next($request);
    }
}
