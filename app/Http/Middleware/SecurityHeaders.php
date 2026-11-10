<?php

namespace App\Http\Middleware;

use App\Support\Http\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every web response. HSTS is only sent over HTTPS.
 *
 * HTML pages also get the Content-Security-Policy (report-only until `csp.enforce`), with a nonce made for this
 * request before the page renders, so the tags that Vite, Livewire and Flux print carry it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Files served inline (photos, PDFs) and Livewire's JSON updates are not documents that run the page's scripts.
        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $policy = new ContentSecurityPolicy($nonce, local: app()->isLocal());

            $response->headers->set($policy->headerName(), $policy->header());
        }

        return $response;
    }
}
