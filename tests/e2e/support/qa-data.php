<?php

// Local environment only. Adds to the demo data what the Security and Reports cases (CP-00-1 to CP-84) need and the
// demo data does not have. Run after `migrate:fresh --seed`; qa-global-setup.ts does it:
//   docker compose exec app php artisan tinker --execute="require 'tests/e2e/support/qa-data.php';"

use App\Actions\Assets\AddAssetComponent;
use App\Actions\Assets\RegisterAssetDamage;
use App\Actions\Assets\RegisterAssetEvent;
use App\Actions\Assets\RegisterMaintenance;
use App\Actions\Assets\RegisterRepair;
use App\Enums\PermissionName as P;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

Model::preventLazyLoading(false);

if (app()->environment('production')) {
    throw new RuntimeException('These accounts use a known password; they are never created in production.');
}

$super = User::query()->where('email', 'superusuario@seti.test')->sole();

$account = function (string $email, string $name, array $roles): User {
    $user = User::query()->firstOrNew(['email' => $email]);
    $user->forceFill(['name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now(), 'deactivated_at' => null])->save();
    $user->syncRoles($roles);

    return $user;
};

$customRole = function (string $name, string $description, array $permissions): Role {
    $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['description' => $description, 'is_system' => false]);
    $role->syncPermissions(array_map(fn (P $p): string => $p->value, $permissions));

    return $role;
};

// CP-60 c, CP-68 d, CP-70 c: may see reports but neither the audit nor export them.
$customRole('Consulta de reportes', 'Prueba: consulta reportes sin auditoría ni exportación.', [P::DashboardsView, P::ReportsView, P::AssetsView, P::SpacesView]);
$account('consulta@seti.test', 'Carla Consulta', ['Consulta de reportes']);

// CP-84 c: may export but not see the history of generated reports.
$customRole('Exporta sin historial', 'Prueba: exporta reportes sin consultar el historial.', [P::DashboardsView, P::ReportsView, P::ReportsExport, P::AssetsView]);
$account('exporta@seti.test', 'Ernesto Exporta', ['Exporta sin historial']);

// CP-00-1 d: a Superusuario with two-factor authentication confirmed (fixed secret, so the test computes the code).
$twoFactor = $account('dosfactores@seti.test', 'Diana Dos Factores', ['Superusuario']);
$twoFactor->forceFill([
    'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'),
    'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
    'two_factor_confirmed_at' => now(),
])->save();

// CP-00-1 e and f: the lockout and the password reset each use their own account.
$account('bloqueo@seti.test', 'Bruno Bloqueo', ['Visualizador']);
$account('recupera@seti.test', 'Rosa Recupera', ['Visualizador']);
// CP-69 c and CP-00-2 b: an account the Superusuario deactivates or whose role changes.
$account('cambios@seti.test', 'Carlos Cambios', ['Reservante']);

$inSpace = fn (string $code) => Asset::query()->whereHas('currentSpace', fn ($q) => $q->where('code', $code))->with(['condition', 'currentSpace'])->orderBy('id');

// CP-66, CP-82: one finished preventive maintenance and one finished repair (the demo data only has a scheduled one).
$lab110 = $inSpace('LAB-110')->get();
app(RegisterMaintenance::class)->handle($super, $lab110[0], [
    'kind' => 'preventive', 'performed_on' => now()->subDays(3)->toDateString(),
    'performed_by' => 'Técnico de prueba', 'description' => 'Limpieza interna y actualización del sistema (prueba QA).',
]);
// A repair needs a damaged asset first (RF_13 → RF_11).
$damage = app(RegisterAssetDamage::class)->handle($super, $lab110[1], [
    'occurred_on' => now()->subDays(2)->toDateString(), 'description' => 'No enciende (prueba QA).',
    'condition_id' => AssetCondition::query()->where('color_group', 'red')->value('id'),
]);
app(RegisterRepair::class)->handle($super, $lab110[1]->refresh(), [
    'asset_event_id' => $damage->id,
    'condition_id' => AssetCondition::query()->where('color_group', 'green')->value('id'),
    'performed_on' => now()->subDays(1)->toDateString(), 'performed_by' => 'Proveedor de prueba',
    'description' => 'Cambio de fuente de poder (prueba QA).', 'status' => 'completed',
]);
app(RegisterMaintenance::class)->handle($super, $lab110[1], [
    'kind' => 'preventive', 'performed_on' => now()->subDays(20)->toDateString(),
    'performed_by' => 'Técnico de prueba', 'description' => 'Revisión general (prueba QA).',
]);

// CP-77: components on two assets.
foreach ([[$lab110[0], ['Memoria RAM', 'DDR4 16 GB', 'RAM-QA-1', 2]], [$lab110[0], ['Disco SSD', 'NVMe 512 GB', 'SSD-QA-1', 1]], [$lab110[2], ['Tarjeta de red', 'PCIe 1 Gb', 'NIC-QA-1', 1]]] as [$asset, [$name, $specs, $serial, $qty]]) {
    app(AddAssetComponent::class)->handle($super, $asset, ['name' => $name, 'specs' => $specs, 'serial_number' => $serial, 'quantity' => $qty, 'notes' => 'Prueba QA']);
}

// CP-75: one expired warranty and one that ends today (the rest of the demo warranties are current).
$lab110[3]->forceFill(['warranty_starts_on' => now()->subYears(3)->toDateString(), 'warranty_ends_on' => now()->subDays(10)->toDateString()])->save();
$lab110[4]->forceFill(['warranty_starts_on' => now()->subYears(2)->toDateString(), 'warranty_ends_on' => now()->toDateString()])->save();

// CP-76: one decommission and one loss.
$lab322 = $inSpace('LAB-322')->get();
app(RegisterAssetEvent::class)->handle($super, $lab322[0], ['kind' => 'decommission', 'occurred_on' => now()->subDays(5)->toDateString(), 'description' => 'Equipo obsoleto (prueba QA).']);
app(RegisterAssetEvent::class)->handle($super, $lab322[1], ['kind' => 'loss', 'occurred_on' => now()->subDays(2)->toDateString(), 'description' => 'No se encontró en el inventario físico (prueba QA).']);

echo 'Datos de QA listos.'.PHP_EOL;
