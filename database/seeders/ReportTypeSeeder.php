<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The read-only report catalog (RF_60 menu; RF_63–RF_79). `code` is what App\Reports\ReportRegistry
 * maps to a report definition class.
 */
class ReportTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['assets_inventory', 'Inventario de activos', 'assets', 'RF_63'],
            ['maintenance', 'Mantenimientos y reparaciones', 'assets', 'RF_66'],
            ['movements', 'Movimientos de inventario', 'assets', 'RF_67'],
            ['warranties', 'Garantías', 'assets', 'RF_75'],
            ['disposals', 'Activos dados de baja o perdidos', 'assets', 'RF_76'],
            ['components', 'Componentes de equipos', 'assets', 'RF_77'],
            ['custodians', 'Responsables de activos', 'assets', 'RF_78'],
            ['spaces', 'Espacios', 'spaces', 'RF_64'],
            ['space_detail', 'Reporte de un espacio', 'spaces', 'RF_74'],
            ['reservations', 'Reservaciones', 'spaces', 'RF_65'],
            ['occupancy', 'Ocupación de espacios', 'spaces', 'RF_79'],
            ['audit', 'Auditoría', 'audit', 'RF_68'],
            ['users', 'Usuarios', 'users', 'RF_69'],
        ];

        DB::table('report_types')->upsert(
            array_map(fn (array $row, int $i): array => [
                'code' => $row[0],
                'name' => $row[1],
                'category' => $row[2],
                'description' => $row[3],
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], $rows, array_keys($rows)),
            uniqueBy: ['code'],
            update: ['name', 'category', 'description', 'sort_order', 'updated_at'],
        );
    }
}
