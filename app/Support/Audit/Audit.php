<?php

namespace App\Support\Audit;

use Illuminate\Support\Facades\Facade;

/**
 * Explicit audit entries for what is not a model create/update/delete (ADR-0002):
 *
 *     Audit::record(AuditAction::RoleAssigned, $user, new: ['role' => $role->name]);
 *     Audit::record(AuditAction::Generated, $generation);
 *
 * @method static \App\Models\AuditLog record(\App\Enums\AuditAction $action, ?\Illuminate\Database\Eloquent\Model $entity = null, ?array $old = null, ?array $new = null, ?string $entityLabel = null, ?\App\Models\User $actor = null)
 *
 * @see Auditor
 */
final class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Auditor::class;
    }
}
