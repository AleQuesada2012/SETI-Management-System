<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\SystemRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The fixed permission catalog and the five protected system roles with their initial matrix
 * (App\Enums\PermissionName, App\Enums\SystemRole; seed.sql). Idempotent: it adds what is missing
 * and never removes permissions granted later.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('permissions')->upsert(
            array_map(fn (PermissionName $permission): array => [
                'name' => $permission->value,
                'guard_name' => 'web',
                'description' => $permission->description(),
                'created_at' => now(),
                'updated_at' => now(),
            ], PermissionName::cases()),
            uniqueBy: ['name', 'guard_name'],
            update: ['description', 'updated_at'],
        );

        DB::table('roles')->upsert(
            array_map(fn (SystemRole $role): array => [
                'name' => $role->value,
                'guard_name' => 'web',
                'description' => $role->description(),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], SystemRole::cases()),
            uniqueBy: ['name', 'guard_name'],
            update: ['description', 'is_system', 'updated_at'],
        );

        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');
        $roleIds = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name');

        $pairs = [];
        foreach (SystemRole::cases() as $role) {
            foreach ($role->permissions() as $permission) {
                $pairs[] = [
                    'permission_id' => $permissionIds[$permission->value],
                    'role_id' => $roleIds[$role->value],
                ];
            }
        }

        DB::table('role_has_permissions')->insertOrIgnore($pairs);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
