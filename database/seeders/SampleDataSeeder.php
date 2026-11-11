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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Temporary test data for the deployed site, so the team can try every module before the handover:
 * spaces in the real campus buildings, ~50 assets placed through real `entry` movements, a repair and a
 * scheduled maintenance, and reservations around the current week. Unlike DemoSeeder it creates no
 * accounts, so it is safe on a public site: it uses the active people already registered.
 *
 *   php artisan db:seed --class=SampleDataSeeder --force
 *
 * Asset tags start with PRUEBA- and spaces carry a note, so the data is recognizable. It is removed with
 * the database reset before the handover (docs/deployment.md), not row by row: history is append-only.
 */
class SampleDataSeeder extends Seeder
{
    public const string NOTE = 'Dato de prueba: se elimina antes de entregar el sistema.';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6: bool}> code => [name, type, status, building, floor, capacity, managed] */
    private const array SPACES = [
        'LAB-101' => ['Laboratorio 101', 'Laboratorio', 'Disponible', 'Torre de Laboratorios', '1', 30, true],
        'LAB-204' => ['Laboratorio 204', 'Laboratorio', 'Disponible', 'Torre de Laboratorios', '2', 24, true],
        'LAB-305' => ['Laboratorio 305', 'Laboratorio', 'En mantenimiento', 'Torre de Laboratorios', '3', 20, true],
        'LAB-REDES' => ['Laboratorio de Redes', 'Laboratorio', 'Disponible', 'Módulo 3', '1', 16, true],
        'LAB-EM' => ['Laboratorio de Electromecánica', 'Laboratorio', 'Disponible', 'Escuela de Electromecánica', '1', 28, true],
        'AULA-M1' => ['Aula 1 del Módulo 1', 'Sala', 'Disponible', 'Módulo 1', '1', 40, true],
        'SAL-ADM' => ['Sala de reuniones administrativa', 'Sala', 'Disponible', 'Edificio Administrativo', '2', 12, true],
        'OF-SETI' => ['Oficina SETI', 'Oficina', 'Disponible', 'Edificio Administrativo', '1', 6, false],
    ];

    public function run(): void
    {
        $taken = $this->spacesAlreadyTaken();
        if ($taken !== []) {
            $this->command->warn('Sample data not loaded: these space codes or names already exist ('.implode(', ', $taken).'). It was probably loaded before.');

            return;
        }

        $people = $this->people();

        DB::transaction(function () use ($people): void {
            $spaces = $this->spaces($people);
            $this->assets($spaces, $people);
            $this->reservations($spaces, $people);
        });

        $this->command->info('Sample data loaded: '.count(self::SPACES).' spaces, their assets and reservations.');
    }

    /** @return list<string> */
    private function spacesAlreadyTaken(): array
    {
        $codes = array_map('mb_strtolower', array_keys(self::SPACES));
        $names = array_map(fn (array $space): string => mb_strtolower($space[0]), array_values(self::SPACES));

        return Space::query()
            ->where(fn ($query) => $query->whereIn(DB::raw('lower(code)'), $codes)->orWhereIn(DB::raw('lower(name)'), $names))
            ->pluck('code')
            ->all();
    }

    /**
     * The active people already registered: one Superusuario is required; Administradores manage the spaces
     * and hold the assets; Reservantes make the bookings. A missing role falls back to the one before it.
     *
     * @return array{super: User, admins: Collection<int, User>, reservers: Collection<int, User>}
     */
    private function people(): array
    {
        $active = fn (SystemRole $role): Collection => User::role($role->value)->whereNull('deactivated_at')->orderBy('id')->get();

        $super = $active(SystemRole::Superusuario)->first()
            ?? throw new RuntimeException('Create the first Superusuario before loading sample data.');

        $admins = $active(SystemRole::Administrador);
        $admins = $admins->isEmpty() ? collect([$super]) : $admins;

        $reservers = $active(SystemRole::Reservante);
        $reservers = $reservers->isEmpty() ? $admins : $reservers;

        return ['super' => $super, 'admins' => $admins->values(), 'reservers' => $reservers->values()];
    }

    /**
     * @param  array{super: User, admins: Collection<int, User>, reservers: Collection<int, User>}  $people
     * @return array<string, Space>
     */
    private function spaces(array $people): array
    {
        $id = fn (string $model, string $name): int => (int) ($model::query()->where('name', $name)->value('id')
            ?? throw new RuntimeException("Missing catalog value \"{$name}\": run php artisan db:seed --force first."));

        $spaces = [];
        $index = 0;
        foreach (self::SPACES as $code => [$name, $type, $status, $building, $floor, $capacity, $managed]) {
            $spaces[$code] = Space::query()->create([
                'code' => $code,
                'name' => $name,
                'space_type_id' => $id(SpaceType::class, $type),
                'space_status_id' => $id(SpaceStatus::class, $status),
                'building_id' => $id(Building::class, $building),
                'floor' => $floor,
                'capacity' => $capacity,
                'notes' => self::NOTE,
            ]);

            if ($managed) {
                $spaces[$code]->managers()->attach($people['admins'][$index++ % $people['admins']->count()]->id, [
                    'assigned_by' => $people['super']->id,
                    'assigned_at' => now(),
                ]);
            }
        }

        return $spaces;
    }

    /**
     * @param  array<string, Space>  $spaces
     * @param  array{super: User, admins: Collection<int, User>, reservers: Collection<int, User>}  $people
     */
    private function assets(array $spaces, array $people): void
    {
        $brands = collect(['Dell', 'HP', 'Lenovo', 'Epson', 'LG', 'Cisco', 'Ubiquiti', 'Samsung', 'Steelcase'])
            ->mapWithKeys(fn (string $name): array => [$name => $this->named(Brand::class, $name)]);
        $technology = $this->named(Supplier::class, 'Distribuidora Tecnológica (prueba)');
        $furniture = $this->named(Supplier::class, 'Mobiliario de Oficina (prueba)');

        $type = fn (string $name): int => (int) AssetType::query()->where('name', $name)->value('id');
        $good = (int) AssetCondition::query()->where('name', 'Buen estado')->value('id');
        $fair = (int) AssetCondition::query()->where('name', 'Regular')->value('id');
        $damaged = (int) AssetCondition::query()->where('name', 'Dañado')->value('id');

        // [space, type, brand, model, count, supplier]
        $inventory = [
            ['LAB-101', 'Computadora de escritorio', 'Dell', 'OptiPlex 7010', 8, $technology],
            ['LAB-101', 'Monitor', 'Dell', 'P2422H', 8, $technology],
            ['LAB-101', 'Proyector', 'Epson', 'EB-X06', 1, $technology],
            ['LAB-204', 'Laptop', 'Dell', 'Latitude 5420', 6, $technology],
            ['LAB-204', 'Aire acondicionado', 'LG', 'Dual Inverter', 1, $technology],
            ['LAB-204', 'Televisor', 'Samsung', 'Crystal UHD 55"', 1, $technology],
            ['LAB-305', 'Computadora de escritorio', 'HP', 'ProDesk 400', 6, $technology],
            ['LAB-REDES', 'Router', 'Cisco', 'ISR 4331', 1, $technology],
            ['LAB-REDES', 'Switch', 'Cisco', 'Catalyst 9200', 2, $technology],
            ['LAB-REDES', 'Access Point', 'Ubiquiti', 'U6 Pro', 2, $technology],
            ['LAB-EM', 'Computadora de escritorio', 'Lenovo', 'ThinkCentre M70', 4, $technology],
            ['LAB-EM', 'Impresora', 'HP', 'LaserJet M404', 1, $technology],
            ['AULA-M1', 'Proyector', 'Epson', 'PowerLite 118', 1, $technology],
            ['AULA-M1', 'Silla', null, null, 6, $furniture],
            ['SAL-ADM', 'Mesa', 'Steelcase', 'Ology', 1, $furniture],
            ['SAL-ADM', 'Televisor', 'LG', 'UHD 65"', 1, $technology],
            ['OF-SETI', 'Caja fuerte', null, null, 1, $furniture],
            ['OF-SETI', 'Laptop', 'Lenovo', 'ThinkPad T14', 2, $technology],
        ];

        $custodians = [...$people['admins']->all(), null];
        $mover = $people['admins'][0];
        $placedAt = CarbonImmutable::now()->subDays(90);
        $tag = 1000;

        foreach ($inventory as [$spaceCode, $typeName, $brandName, $model, $count, $supplier]) {
            for ($i = 0; $i < $count; $i++) {
                $tag++;
                $acquired = CarbonImmutable::now()->subDays(random_int(120, 1500));
                $withWarranty = ! in_array($typeName, ['Silla', 'Mesa', 'Caja fuerte'], true);

                $asset = Asset::query()->create([
                    'asset_tag' => $tag % 9 === 0 ? null : "PRUEBA-{$tag}",
                    'serial_number' => $brandName === null ? null : strtoupper(substr($brandName, 0, 3)).'-'.random_int(100000, 999999),
                    'asset_type_id' => $type($typeName),
                    'brand_id' => $brandName === null ? null : $brands[$brandName]->id,
                    'model' => $model,
                    'condition_id' => $tag % 11 === 0 ? $fair : $good,
                    'responsible_user_id' => $custodians[$tag % count($custodians)]?->id,
                    'supplier_id' => $supplier->id,
                    'acquired_on' => $acquired->toDateString(),
                    'warranty_starts_on' => $withWarranty ? $acquired->toDateString() : null,
                    'warranty_ends_on' => $withWarranty ? $acquired->addYears(3)->toDateString() : null,
                    'observations' => self::NOTE,
                ]);

                AssetMovement::query()->create([
                    'asset_id' => $asset->id,
                    'type' => MovementType::Entry,
                    'to_space_id' => $spaces[$spaceCode]->id,
                    'moved_at' => $placedAt,
                    'moved_by' => $mover->id,
                    'reason' => 'Carga de datos de prueba',
                ]);
            }
        }

        // A damaged air conditioner under repair, and a scheduled preventive check (dashboard KPIs).
        $airConditioner = $spaces['LAB-204']->assets()->where('asset_type_id', $type('Aire acondicionado'))->firstOrFail();
        $damage = AssetEvent::query()->create([
            'asset_id' => $airConditioner->id,
            'kind' => AssetEventKind::Damage,
            'occurred_on' => now()->subDays(5)->toDateString(),
            'description' => 'No enfría; posible fuga de refrigerante.',
            'registered_by' => $mover->id,
        ]);
        $airConditioner->forceFill(['condition_id' => $damaged]);
        $airConditioner->transitionTo(AssetStatus::InMaintenance);
        MaintenanceRecord::query()->create([
            'asset_id' => $airConditioner->id,
            'kind' => MaintenanceKind::Corrective,
            'status' => MaintenanceStatus::InProgress,
            'started_on' => now()->subDays(3)->toDateString(),
            'description' => 'Revisión y recarga de refrigerante.',
            'performed_by' => 'Climatización Técnica (prueba)',
            'asset_event_id' => $damage->id,
            'registered_by' => $mover->id,
        ]);

        $projector = $spaces['LAB-101']->assets()->where('asset_type_id', $type('Proyector'))->firstOrFail();
        MaintenanceRecord::query()->create([
            'asset_id' => $projector->id,
            'kind' => MaintenanceKind::Preventive,
            'status' => MaintenanceStatus::Scheduled,
            'scheduled_for' => now()->addDays(10)->toDateString(),
            'description' => 'Limpieza de filtros y revisión de horas de lámpara.',
            'registered_by' => $mover->id,
        ]);
    }

    /**
     * @param  array<string, Space>  $spaces
     * @param  array{super: User, admins: Collection<int, User>, reservers: Collection<int, User>}  $people
     */
    private function reservations(array $spaces, array $people): void
    {
        $today = CarbonImmutable::today();
        $reserver = fn (int $n): User => $people['reservers'][$n % $people['reservers']->count()];

        $book = function (string $code, CarbonImmutable $day, string $from, string $to, string $title, int $attendees, User $by, ?string $series = null) use ($spaces): Reservation {
            [$fromHour, $fromMinute] = array_map('intval', explode(':', $from));
            [$toHour, $toMinute] = array_map('intval', explode(':', $to));

            return Reservation::query()->create([
                'space_id' => $spaces[$code]->id,
                'starts_at' => $day->setTime($fromHour, $fromMinute),
                'ends_at' => $day->setTime($toHour, $toMinute),
                'title' => $title,
                'attendees' => $attendees,
                'responsible_id' => $by->id,
                'created_by' => $by->id,
                'series_id' => $series,
            ]);
        };

        $book('LAB-REDES', $today, '09:00', '11:00', 'Torneo de programación', 16, $reserver(0));
        $book('LAB-101', $today, '16:30', '18:00', 'Tutoría grupal', 12, $reserver(1));
        $book('LAB-204', $today->addDay(), '13:00', '16:00', 'Charla de bienvenida', 20, $people['admins'][0]);
        $book('SAL-ADM', $today->addDays(2), '10:00', '11:00', 'Reunión de coordinación', 8, $people['super']);
        $book('AULA-M1', $today->addDays(2), '07:30', '09:20', 'Clase de repaso', 35, $reserver(2));
        $book('LAB-EM', $today->addDays(4), '13:00', '15:00', 'Práctica de controladores', 20, $reserver(0));
        $book('LAB-101', $today->subDays(7), '08:00', '10:00', 'Práctica de redes', 25, $reserver(1));

        $cancelled = $book('LAB-EM', $today->addDays(3), '14:00', '15:00', 'Revisión de proyecto', 6, $reserver(2));
        $cancelled->forceFill([
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $cancelled->created_by,
            'cancellation_reason' => 'El grupo reprogramó la revisión.',
        ])->save();

        // A weekly series (CONTEXT.md "Reservation series"): the same booking on three dates.
        $series = (string) str()->uuid();
        foreach ([7, 14, 21] as $days) {
            $book('LAB-204', $today->addDays($days), '10:00', '12:00', 'Curso de bases de datos', 20, $reserver(0), $series);
        }
    }

    /**
     * An existing brand or supplier with that name (any case), or a new one.
     *
     * @template TModel of Brand|Supplier
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function named(string $model, string $name): Brand|Supplier
    {
        return $model::query()->whereRaw('lower(name) = lower(?)', [$name])->first()
            ?? $model::query()->create(['name' => $name]);
    }
}
