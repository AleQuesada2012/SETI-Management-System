<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Initial catalog values agreed in the design (docs/db/seed.sql). Idempotent: rows that already
 * exist (case-insensitively, among non-deleted rows) are left alone, so the Superusuario's later
 * edits are never overwritten.
 *
 * Seeders write with the query builder on purpose: like direct SQL, they are not audited.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // The buildings of the Centro Académico de Alajuela; a space's location is building + floor + detail.
        $this->seedNamed('buildings', [
            ['name' => 'Módulo 1'],
            ['name' => 'Módulo 2'],
            ['name' => 'Módulo 3'],
            ['name' => 'Módulo 4'],
            ['name' => 'Edificio Administrativo'],
            ['name' => 'Módulo 6'],
            ['name' => 'Edificio TEC'],
            ['name' => 'Torre de Laboratorios'],
            ['name' => 'Escuela de Electromecánica'],
        ]);

        $this->seedNamed('space_types', [
            ['name' => 'Laboratorio', 'is_reservable' => true, 'description' => 'Laboratorio de cómputo o de proyectos. Se puede reservar.'],
            ['name' => 'Sala', 'is_reservable' => true, 'description' => 'Sala de reuniones o aula. Se puede reservar.'],
            ['name' => 'Oficina', 'is_reservable' => false, 'description' => 'Oficina administrativa. No se reserva.'],
        ]);

        $this->seedNamed('space_statuses', [
            ['name' => 'Disponible', 'allows_reservations' => true, 'color_group' => 'green', 'sort_order' => 1],
            ['name' => 'En mantenimiento', 'allows_reservations' => false, 'color_group' => 'amber', 'sort_order' => 2],
            ['name' => 'Inhabilitado', 'allows_reservations' => false, 'color_group' => 'red', 'sort_order' => 3],
        ]);

        $this->seedNamed('asset_conditions', [
            ['name' => 'Buen estado', 'color_group' => 'green', 'description' => 'Funciona correctamente y no presenta daños.', 'sort_order' => 1],
            ['name' => 'Regular', 'color_group' => 'amber', 'description' => 'Funciona, pero con desgaste o fallas menores.', 'sort_order' => 2],
            ['name' => 'Dañado', 'color_group' => 'red', 'description' => 'No funciona o tiene daños que requieren reparación.', 'sort_order' => 3],
        ]);

        $this->seedNamed('asset_categories', [
            ['name' => 'Cómputo', 'description' => 'Equipos de cómputo y periféricos.'],
            ['name' => 'Redes', 'description' => 'Equipos de red y comunicaciones.'],
            ['name' => 'Audiovisual', 'description' => 'Equipos de proyección, video y audio.'],
            ['name' => 'Mobiliario', 'description' => 'Muebles y almacenamiento.'],
            ['name' => 'Otros', 'description' => 'Activos que no encajan en las demás categorías.'],
        ]);

        $types = [
            'Cómputo' => ['Computadora de escritorio', 'Laptop', 'Monitor', 'Teclado', 'Mouse', 'Impresora', 'Servidor'],
            'Redes' => ['Switch', 'Router', 'Access Point'],
            'Audiovisual' => ['Proyector', 'Televisor', 'Parlante'],
            'Mobiliario' => ['Escritorio', 'Silla', 'Mesa', 'Armario', 'Gavetero', 'Caja fuerte'],
            'Otros' => ['Ventilador', 'Aire acondicionado', 'Otro'],
        ];

        foreach ($types as $category => $names) {
            $categoryId = DB::table('asset_categories')
                ->whereRaw('lower(name) = lower(?)', [$category])
                ->whereNull('deleted_at')
                ->value('id');

            $this->seedNamed('asset_types', array_map(
                fn (string $name): array => ['asset_category_id' => $categoryId, 'name' => $name],
                $names,
            ));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedNamed(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $exists = DB::table($table)
                ->whereRaw('lower(name) = lower(?)', [$row['name']])
                ->whereNull('deleted_at')
                ->exists();

            if (! $exists) {
                DB::table($table)->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
