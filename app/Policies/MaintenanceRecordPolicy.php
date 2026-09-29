<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;

/**
 * Maintenance and repairs (RF_10, RF_11, RF_26, RF_27). Managing is space-scoped (★) through the asset.
 *
 *     $this->authorize('create', [MaintenanceRecord::class, $asset]);
 */
class MaintenanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::MaintenanceView);
    }

    public function view(User $user, MaintenanceRecord $record): bool
    {
        return $user->can(P::MaintenanceView);
    }

    public function create(User $user, ?Asset $asset = null): bool
    {
        return $asset === null
            ? $user->isSuperuser() || ($user->can(P::MaintenanceManage) && $user->managesAnySpace())
            : $user->canInSpace(P::MaintenanceManage, $asset->responsibleSpaceId());
    }

    public function update(User $user, MaintenanceRecord $record): bool
    {
        return $user->canInSpace(P::MaintenanceManage, $record->asset->responsibleSpaceId());
    }

    public function delete(User $user, MaintenanceRecord $record): bool
    {
        return $this->update($user, $record);
    }
}
