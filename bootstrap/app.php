<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceSessionLifetime;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottlePasswordResetLinks;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware(SecurityHeaders::class)->group(base_path('routes/powerbi.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // In production Caddy terminates HTTPS and forwards over the private Docker network (compose.prod.yaml).
        // Trusting only private ranges keeps audit IPs honest: a client cannot spoof X-Forwarded-For.
        $middleware->trustProxies(at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        $middleware->web(append: [
            AssignRequestId::class,
            EnsureUserIsActive::class,
            EnforceSessionLifetime::class,
            RequireTwoFactor::class,
            ThrottlePasswordResetLinks::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
