<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * RF_02 · Delete an asset that was registered by mistake. It is a soft delete (Agent-DB.md §7, Q3): the asset leaves
 * every list and report, its movements, events and audit entries stay, and its tag becomes free for reuse. It is never
 * a decommission (RF_29), which is an AssetEvent and keeps the asset listed. Any asset may be deleted, whatever its
 * status (decision of 2026-10-07).
 *
 * An Administrador deletes only assets in, or last in, a space they manage (★, AssetPolicy::delete). The `deleted`
 * audit entry is written by the Auditable asset.
 *
 * @throws ModelNotFoundException when the asset no longer exists (deleted meanwhile, e.g. from another tab)
 */
final class DeleteAsset
{
    public function handle(User $actor, Asset $asset): Asset
    {
        Gate::forUser($actor)->authorize('delete', $asset);

        return DB::transaction(function () use ($asset): Asset {
            $current = Asset::query()->whereKey($asset->id)->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException)->setModel(Asset::class, [$asset->id]);

            $current->delete();

            return $current;
        });
    }
}
