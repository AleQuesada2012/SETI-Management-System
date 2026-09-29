<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * The right to perform one kind of operation (CONTEXT.md "Permission"). The catalog is fixed:
 * see App\Enums\PermissionName.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 */
class Permission extends SpatiePermission {}
