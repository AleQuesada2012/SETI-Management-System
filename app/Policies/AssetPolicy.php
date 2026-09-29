<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Asset;
use App\Models\Space;
use App\Models\User;

/**
 * Who may do what with assets (RF_89). The Superusuario passes every check (Gate::before). Mutations are
 * space-scoped (★): an Administrador acts only on assets in, or last in, the spaces they manage.
 *
 * Livewire action: $this->authorize('update', $asset);
 * Blade:           @can('create', [App\Models\Asset::class, $space]) … @endcan
 */
class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::AssetsView);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can(P::AssetsView);
    }

    /** RF_03. Without a space: may the user register assets at all (shows the "Añadir activo" button)? */
    public function create(User $user, ?Space $space = null): bool
    {
        return $space === null
            ? $user->isSuperuser() || ($user->can(P::AssetsCreate) && $user->managesAnySpace())
            : $user->canInSpace(P::AssetsCreate, $space);
    }

    /** RF_04, RF_05, RF_14–RF_19, RF_23, RF_24. */
    public function update(User $user, Asset $asset): bool
    {
        return $user->canInSpace(P::AssetsUpdate, $asset->responsibleSpaceId());
    }

    /** RF_02: a record entered by mistake. Decommissioning (RF_29) is registerEvent. */
    public function delete(User $user, Asset $asset): bool
    {
        return $user->canInSpace(P::AssetsDelete, $asset->responsibleSpaceId());
    }

    /** RF_06, RF_25: entries, transfers and exits of an asset that is (or was last) in a managed space. */
    public function move(User $user, Asset $asset): bool
    {
        return $user->canInSpace(P::AssetsMove, $asset->responsibleSpaceId());
    }

    /** RF_13, RF_22, RF_28, RF_29: damage, loss, recovery and decommission. */
    public function registerEvent(User $user, Asset $asset): bool
    {
        return $user->canInSpace(P::AssetsRegisterEvents, $asset->responsibleSpaceId());
    }

    /** RF_12. */
    public function manageComponents(User $user, Asset $asset): bool
    {
        return $user->canInSpace(P::AssetsManageComponents, $asset->responsibleSpaceId());
    }

    /** RF_20, RF_21. */
    public function manageFiles(User $user, Asset $asset): bool
    {
        return $user->canInSpace(P::AssetsManageFiles, $asset->responsibleSpaceId());
    }
}
