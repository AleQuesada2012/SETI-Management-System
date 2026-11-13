<?php

// Local environment only. Creates the stress-test accounts the JMeter plans in tests/stress/ sign in with, and one
// exported report for the download scenario. Run from the repository root:
//   docker compose exec app php artisan tinker --execute="require 'tests/stress/create-stress-accounts.php';"
// Safe to run again: it reuses what already exists.

use App\Actions\Reports\ExportReport;
use App\Enums\PermissionName as P;
use App\Enums\ReportFormat;
use App\Models\ReportGeneration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

if (app()->environment('production')) {
    throw new RuntimeException('The stress accounts use a known password; they are never created in production.');
}

// A custom role, so the accounts sign in without two-factor authentication (only the Superusuario and the
// Administradores must use it). Read-only permissions of every module, plus export for the download scenario.
$role = Role::query()->firstOrCreate(
    ['name' => 'Pruebas de estrés', 'guard_name' => 'web'],
    ['description' => 'Cuentas locales de las pruebas de estrés con JMeter.', 'is_system' => false],
);
$role->syncPermissions(array_map(fn (P $p): string => $p->value, [
    P::AssetsView, P::MaintenanceView, P::SpacesView, P::ReservationsView, P::DashboardsView,
    P::ReportsView, P::ReportsExport, P::ReportsAudit, P::ReportsHistory, P::UsersView, P::RolesView,
]));

// estres01–estres20: cuentas.csv (pages). estres21–estres30: cuentas-login.csv (sign-in). estres31: lockout.
foreach (range(1, 31) as $i) {
    $email = sprintf('estres%02d@seti.test', $i);
    $user = User::query()->firstOrNew(['email' => $email]);
    $user->forceFill([
        'name' => sprintf('Estrés %02d', $i),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'deactivated_at' => null,
    ])->save();
    $user->syncRoles([$role->name]);
}

$owner = User::query()->where('email', 'estres01@seti.test')->sole();
$generation = ReportGeneration::query()->where('generated_by', $owner->id)->latest('id')->first()
    ?? app(ExportReport::class)->handle($owner, 'assets_inventory', ReportFormat::Pdf);

echo "Listo: 31 cuentas estresNN@seti.test con el rol \"{$role->name}\".".PHP_EOL;
echo "Reporte generado para la descarga: -JgenerationId={$generation->id}".PHP_EOL;
