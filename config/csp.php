<?php

// Content-Security-Policy sent by App\Http\Middleware\SecurityHeaders (directives in App\Support\Http\ContentSecurityPolicy).
return [
    // false sends Content-Security-Policy-Report-Only: the browser reports what the policy would block (in its console)
    // and blocks nothing. Set CSP_ENFORCE=true once a deployed copy reports no violations.
    'enforce' => (bool) env('CSP_ENFORCE', false),

    // Where the browser opens Reverb's WebSocket: Caddy proxies /app/* on the site's own host (compose.prod.yaml), so
    // by default wss:// to the host of APP_URL. resources/js/echo.js connects to the same place (VITE_REVERB_HOST).
    'websocket' => env('CSP_WEBSOCKET', 'wss://'.parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    // Allowed only when APP_ENV=local (compose.yaml): Reverb's forwarded port and the Vite dev server, which serves the
    // scripts, styles and fonts and opens its own WebSocket for hot reload.
    'local' => [
        'websocket' => 'ws://localhost:'.env('REVERB_FORWARD_PORT', 8080),
        'vite' => 'localhost:'.env('VITE_PORT', 5173),
    ],
];
