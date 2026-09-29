<?php

namespace App\Http\Middleware;

use App\Support\Audit\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every HTTP request (Livewire updates included) its own id. It groups the audit entries
 * written by one user action, appears in log lines, and is returned as X-Request-Id.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid7();
        Context::add(AuditContext::REQUEST_ID, $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
