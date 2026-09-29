<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A named bundle of permissions (CONTEXT.md "Role"). A user holds one or more (ADR-0008).
 * System roles (is_system) are seeded and can be neither renamed nor deleted (enforced in Actions).
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
