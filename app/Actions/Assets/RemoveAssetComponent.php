<?php

namespace App\Actions\Assets;

use App\Models\AssetComponent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * RF_12 · Delete a component of an asset. Nothing points to a component, so this is a hard delete; the `deleted` audit
 * entry, written by the Auditable component, keeps its values (Agent-DB.md §7, §16 rule 5).
 */
final class RemoveAssetComponent
{
    public function handle(User $actor, AssetComponent $component): void
    {
        $asset = $component->asset()->firstOrFail(); // fresh: a loaded relation may predate a decommission

        Gate::forUser($actor)->authorize('manageComponents', $asset);
        AddAssetComponent::ensureNotDecommissioned($asset);

        DB::transaction(fn () => $component->delete());
    }
}
