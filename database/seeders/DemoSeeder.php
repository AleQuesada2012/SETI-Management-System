<?php

namespace Database\Seeders;

use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Enums\MovementType;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetEvent;
use App\Models\AssetMovement;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Building;
use App\Models\MaintenanceRecord;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Development and sprint-demo data: the mockup's rooms, fictional people (one login per role,
 * password "password"), ~50 assets placed through real `entry` movements, and reservations
 * around the current week. Never run it in production.
 *
 * Logins: superusuario@seti.test, admin@seti.test, admin2@seti.test, reportes@seti.test,
 * visualizador@seti.test, reservante@seti.test, profesora@seti.test (Visualizador + Reservante).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder creates users with a known password; it never runs in production.');
        }

        if (User::query()->where('email', 'superusuario@seti.test')->exists()) {
            $this->command->warn('Demo data already present; skipping DemoSeeder.');

            return;
        }

        DB::transaction(function (): void {
            $users = $this->users();
            $spaces = $this->spaces($users);
            $this->assets($spaces, $users);
            $this->reservations($spaces, $users);
        });
    }

    /**
     * @return array<string, User>
     */
    private function users(): array
    {
        $people = [
            'super' => ['Sofía Vargas', 'superusuario@seti.test', [SystemRole::Superusuario]],
            'admin' => ['Mario Solís', 'admin@seti.test', [SystemRole::Administrador]],
            'admin2' => ['Lucía Campos', 'admin2@seti.test', [SystemRole::Administrador]],
            'reportes' => ['Andrés Mora', 'reportes@seti.test', [SystemRole::Reportes]],
            'visualizador' => ['Paula Jiménez', 'visualizador@seti.test', [SystemRole::Visualizador]],
            'reservante' => ['Esteban Castro', 'reservante@seti.test', [SystemRole::Reservante]],
            'profesora' => ['Daniela Quirós', 'profesora@seti.test', [SystemRole::Visualizador, SystemRole::Reservante]],
        ];

        // Demo accounts sign in with the password alone; with TWO_FACTOR_REQUIRED on, the Superusuario and the
        // Administradores are sent to set up two-factor authentication, so it can be demonstrated.
        $users = [];
        foreach ($people as $key => [$name, $email, $roles]) {
            $users[$key] = User::factory()->withRole(...$roles)->withoutTwoFactor()->create(['name' => $name, 'email' => $email]);
        }

        User::factory()->withRole(SystemRole::Visualizador)->deactivated()
            ->create(['name' => 'Jorge Ramírez', 'email' => 'inactivo@seti.test']);
        User::factory()->withRole(SystemRole::Reservante)->invited()
            ->create(['name' => 'Valeria Soto', 'email' => 'invitada@seti.test']);

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, Space>
     */
    private function spaces(array $users): array
    {
        $a = Building::query()->create(['name' => 'Edificio A']);
        $b = Building::query()->create(['name' => 'Edificio B']);

        $type = fn (string $name): int => (int) SpaceType::query()->where('name', $name)->value('id');
        $status = fn (string $name): int => (int) SpaceStatus::query()->where('name', $name)->value('id');

        $rows = [
            'LAB-110' => ['Laboratorio 110', 'Laboratorio', 'Disponible', $a, '1', 30, 'admin'],
            'LAB-312' => ['Laboratorio 312', 'Laboratorio', 'Disponible', $b, '3', 24, 'admin'],
            'LAB-316' => ['Laboratorio 316', 'Laboratorio', 'En mantenimiento', $b, '3', 20, 'admin2'],
            'LAB-322' => ['Laboratorio 322', 'Laboratorio', 'Disponible', $b, '3', 28, 'admin2'],
            'LAIMI' => ['LAIMI', 'Laboratorio', 'Disponible', $a, '2', 16, 'admin2'],
            'SAL-01' => ['Sala de reuniones', 'Sala', 'Disponible', $a, '1', 12, 'admin'],
            'OF-SETI' => ['Oficina SETI', 'Oficina', 'Disponible', $a, '1', 6, null],
        ];

        $spaces = [];
        foreach ($rows as $code => [$name, $typeName, $statusName, $building, $floor, $capacity, $manager]) {
            $spaces[$code] = Space::query()->create([
                'code' => $code,
                'name' => $name,
                'space_type_id' => $type($typeName),
                'space_status_id' => $status($statusName),
                'building_id' => $building->id,
                'floor' => $floor,
                'capacity' => $capacity,
            ]);

            if ($manager !== null) {
                $spaces[$code]->managers()->attach($users[$manager]->id, [
                    'assigned_by' => $users['super']->id,
                    'assigned_at' => now(),
                ]);
            }
        }

        return $spaces;
    }

    /**
     * @param  array<string, Space>  $spaces
     * @param  array<string, User>  $users
     */
    private function assets(array $spaces, array $users): void
    {
        $brands = collect(['Dell', 'HP', 'Lenovo', 'Epson', 'LG', 'Cisco', 'Ubiquiti', 'Samsung', 'Herman Miller', 'Steelcase'])
            ->mapWithKeys(fn (string $name): array => [$name => Brand::query()->create(['name' => $name])]);

        $supplier = Supplier::query()->create(['name' => 'Distribuidora Tecnológica S.A.', 'tax_id' => '3-101-123456']);
        $furniture = Supplier::query()->create(['name' => 'Mobiliario de Oficina CR', 'tax_id' => '3-101-654321']);

        $type = fn (string $name): int => (int) AssetType::query()->where('name', $name)->value('id');
        $good = (int) AssetCondition::query()->where('name', 'Buen estado')->value('id');
        $fair = (int) AssetCondition::query()->where('name', 'Regular')->value('id');
        $damaged = (int) AssetCondition::query()->where('name', 'Dañado')->value('id');

        // [space, type, brand, model, count, supplier]
        $inventory = [
            ['LAB-110', 'Computadora de escritorio', 'Dell', 'OptiPlex 7010', 8, $supplier],
            ['LAB-110', 'Monitor', 'Dell', 'P2422H', 8, $supplier],
            ['LAB-110', 'Proyector', 'Epson', 'EB-X06', 1, $supplier],
            ['LAB-110', 'Silla', 'Herman Miller', 'Aeron', 4, $furniture],
            ['LAB-312', 'Laptop', 'Dell', 'Latitude 5420', 6, $supplier],
            ['LAB-312', 'Aire acondicionado', 'LG', 'Dual Inverter', 1, $supplier],
            ['LAB-312', 'Televisor', 'Samsung', 'Crystal UHD 55"', 1, $supplier],
            ['LAB-322', 'Computadora de escritorio', 'HP', 'ProDesk 400', 6, $supplier],
            ['LAB-322', 'Impresora', 'HP', 'LaserJet M404', 1, $supplier],
            ['LAIMI', 'Router', 'Cisco', 'ISR 4331', 1, $supplier],
            ['LAIMI', 'Switch', 'Cisco', 'Catalyst 9200', 2, $supplier],
            ['LAIMI', 'Access Point', 'Ubiquiti', 'U6 Pro', 2, $supplier],
            ['SAL-01', 'Mesa', 'Steelcase', 'Ology', 1, $furniture],
            ['SAL-01', 'Proyector', 'Epson', 'PowerLite 118', 1, $supplier],
            ['OF-SETI', 'Caja fuerte', null, null, 1, $furniture],
            ['OF-SETI', 'Laptop', 'Lenovo', 'ThinkPad T14', 2, $supplier],
        ];

        $custodians = [$users['admin'], $users['admin2'], null];
        $tag = 1000;
        $placedAt = CarbonImmutable::now()->subDays(90);

        foreach ($inventory as [$spaceCode, $typeName, $brandName, $model, $count, $from]) {
            for ($i = 0; $i < $count; $i++) {
                $tag++;
                $acquired = CarbonImmutable::now()->subDays(random_int(120, 1500));
                $withWarranty = $typeName !== 'Silla' && $typeName !== 'Mesa' && $typeName !== 'Caja fuerte';

                $asset = Asset::query()->create([
                    'asset_tag' => $tag % 9 === 0 ? null : "TEC-{$tag}",
                    'serial_number' => $brandName === null ? null : strtoupper(substr($brandName, 0, 3)).'-'.random_int(100000, 999999),
                    'asset_type_id' => $type($typeName),
                    'brand_id' => $brandName === null ? null : $brands[$brandName]->id,
                    'model' => $model,
                    'condition_id' => $tag % 11 === 0 ? $fair : $good,
                    'responsible_user_id' => $custodians[$tag % 3]?->id,
                    'supplier_id' => $from->id,
                    'acquired_on' => $acquired->toDateString(),
                    'warranty_starts_on' => $withWarranty ? $acquired->toDateString() : null,
                    'warranty_ends_on' => $withWarranty ? $acquired->addYears(3)->toDateString() : null,
                ]);

                AssetMovement::query()->create([
                    'asset_id' => $asset->id,
                    'type' => MovementType::Entry,
                    'to_space_id' => $spaces[$spaceCode]->id,
                    'moved_at' => $placedAt,
                    'moved_by' => $users['admin']->id,
                    'reason' => 'Carga inicial del inventario',
                ]);
            }
        }

        // A damaged air conditioner under repair, and a scheduled preventive check (dashboard KPIs).
        $airConditioner = Asset::query()->where('asset_type_id', $type('Aire acondicionado'))->firstOrFail();
        $damage = AssetEvent::query()->create([
            'asset_id' => $airConditioner->id,
            'kind' => AssetEventKind::Damage,
            'occurred_on' => now()->subDays(5)->toDateString(),
            'description' => 'No enfría; posible fuga de refrigerante.',
            'registered_by' => $users['admin']->id,
        ]);
        $airConditioner->forceFill(['condition_id' => $damaged, 'status' => AssetStatus::InMaintenance])->save();
        MaintenanceRecord::query()->create([
            'asset_id' => $airConditioner->id,
            'kind' => MaintenanceKind::Corrective,
            'status' => MaintenanceStatus::InProgress,
            'started_on' => now()->subDays(3)->toDateString(),
            'description' => 'Revisión y recarga de refrigerante.',
            'performed_by' => 'Climatización Técnica S.A.',
            'asset_event_id' => $damage->id,
            'registered_by' => $users['admin']->id,
        ]);

        $projector = Asset::query()->where('asset_type_id', $type('Proyector'))->firstOrFail();
        MaintenanceRecord::query()->create([
            'asset_id' => $projector->id,
            'kind' => MaintenanceKind::Preventive,
            'status' => MaintenanceStatus::Scheduled,
            'scheduled_for' => now()->addDays(10)->toDateString(),
            'description' => 'Limpieza de filtros y revisión de horas de lámpara.',
            'registered_by' => $users['admin']->id,
        ]);
    }

    /**
     * @param  array<string, Space>  $spaces
     * @param  array<string, User>  $users
     */
    private function reservations(array $spaces, array $users): void
    {
        $today = CarbonImmutable::today();

        $book = function (string $code, CarbonImmutable $day, string $from, string $to, string $title, int $attendees, User $responsible, User $creator, ?string $series = null) use ($spaces): Reservation {
            [$fromHour, $fromMinute] = array_map('intval', explode(':', $from));
            [$toHour, $toMinute] = array_map('intval', explode(':', $to));

            return Reservation::query()->create([
                'space_id' => $spaces[$code]->id,
                'starts_at' => $day->setTime($fromHour, $fromMinute),
                'ends_at' => $day->setTime($toHour, $toMinute),
                'title' => $title,
                'attendees' => $attendees,
                'responsible_id' => $responsible->id,
                'created_by' => $creator->id,
                'series_id' => $series,
            ]);
        };

        $book('LAIMI', $today, '09:00', '11:00', 'Torneo de programación', 16, $users['profesora'], $users['profesora']);
        $book('LAB-110', $today, '16:30', '18:00', 'Tutoría grupal', 12, $users['reservante'], $users['reservante']);
        $book('LAB-312', $today->addDay(), '13:00', '16:00', 'Charla de bienvenida', 20, $users['admin'], $users['admin']);
        $book('SAL-01', $today->addDays(2), '10:00', '11:00', 'Reunión de coordinación', 8, $users['super'], $users['super']);
        $book('LAB-110', $today->subDays(7), '08:00', '10:00', 'Práctica de redes', 25, $users['profesora'], $users['profesora']);

        $cancelled = $book('LAB-322', $today->addDays(3), '14:00', '15:00', 'Revisión de proyecto', 6, $users['reservante'], $users['reservante']);
        $cancelled->forceFill([
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $users['reservante']->id,
            'cancellation_reason' => 'El grupo reprogramó la revisión.',
        ])->save();

        // A weekly series (CONTEXT.md "Reservation series"): same booking on three dates.
        $series = (string) str()->uuid();
        foreach ([7, 14, 21] as $days) {
            $book('LAB-312', $today->addDays($days), '10:00', '12:00', 'Curso de bases de datos', 20, $users['profesora'], $users['profesora'], $series);
        }
    }
}
