<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
 * RF_89 guard for every future route: a route that needs a signed-in user must also check a `can:` ability,
 * unless it only touches the user's own account or session (the allowlist below). When this fails, add
 * ->middleware('can:…') to the route named in the message (see routes/modules/*.php).
 */

const OWN_ACCOUNT_ROUTES = [
    'start',                                    // redirects to the first module the user may open
    'profile.edit', 'security.edit', 'appearance.edit', null, // own settings (null: the /configuracion redirect)
    'logout', 'password.confirm', 'password.confirm.store', 'password.confirmation',
    'verification.notice', 'verification.verify', 'verification.send',
    'two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.qr-code',
    'two-factor.secret-key', 'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes',
];

test('every authenticated route checks an ability', function () {
    $authenticated = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => in_array('auth', $route->gatherMiddleware(), true)
            || collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:')));

    expect($authenticated->map->getName())->toContain('assets.index', 'reports.generations.download');

    $authenticated
        ->reject(fn (Route $route) => in_array($route->getName(), OWN_ACCOUNT_ROUTES, true))
        ->each(function (Route $route) {
            $hasAbility = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:'));

            expect($hasAbility)->toBeTrue("Route [{$route->getName()}] ({$route->uri()}) has no `can:` middleware (RF_89).");
        });
});
