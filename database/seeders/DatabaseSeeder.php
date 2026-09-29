<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan migrate --seed` → reference data every environment needs (Agent-DB.md / seed.sql).
 * In the local environment it also loads the demo dataset used for development and sprint demos.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            ReportTypeSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
