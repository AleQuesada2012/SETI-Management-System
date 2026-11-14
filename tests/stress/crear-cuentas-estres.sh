#!/usr/bin/env bash
#
# Creates the accounts the JMeter plans in tests/stress sign in with (test plan, "Pruebas de estrés"): the custom role
# "Pruebas de estrés" with the read-only permissions of every module, and estres1@seti.test … estresN@seti.test with
# the password "password". Custom roles never ask for two-factor authentication, and one account per thread keeps
# every thread under the login limit (5 attempts per minute per account).
#
# Local environment only. Run from the repository root with the stack up (docker compose up -d):
#
#   tests/stress/crear-cuentas-estres.sh [accounts=20]
#
# Safe to run twice: it reuses the role and the accounts that already exist.
set -euo pipefail

ACCOUNTS="${1:-20}"

docker compose exec -T app php artisan tinker --execute "
    if (app()->isProduction()) {
        throw new RuntimeException('Las cuentas de estrés tienen una contraseña conocida; solo se crean en el ambiente local.');
    }

    \$role = App\Models\Role::query()->firstOrCreate(
        ['name' => 'Pruebas de estrés', 'guard_name' => 'web'],
        ['description' => 'Cuentas de las pruebas de estrés con JMeter (solo ambiente local).', 'is_system' => false],
    );
    \$role->syncPermissions(array_map(fn (App\Enums\PermissionName \$p): string => \$p->value, [
        App\Enums\PermissionName::AssetsView,
        App\Enums\PermissionName::MaintenanceView,
        App\Enums\PermissionName::SpacesView,
        App\Enums\PermissionName::ReservationsView,
        App\Enums\PermissionName::DashboardsView,
        App\Enums\PermissionName::ReportsView,
        App\Enums\PermissionName::UsersView,
        App\Enums\PermissionName::RolesView,
    ]));

    foreach (range(1, $ACCOUNTS) as \$i) {
        \$user = App\Models\User::query()->firstOrNew(['email' => \"estres{\$i}@seti.test\"]);
        \$user->forceFill([
            'name' => \"Prueba de estrés {\$i}\",
            'password' => 'password',
            'email_verified_at' => \$user->email_verified_at ?? now(),
        ])->save();
        \$user->syncRoles([\$role->name]);
    }

    echo 'Rol \"Pruebas de estrés\" y cuentas estres1@seti.test a estres$ACCOUNTS@seti.test listos (contraseña: password).', PHP_EOL;
"
