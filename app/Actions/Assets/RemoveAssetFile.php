<?php

namespace App\Actions\Assets;

use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\File;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * RF_20, RF_21 · Remove a document or a photo from an asset. Space-scoped (★): `manageFiles`. Attachments are
 * hard-deleted (Agent-DB.md §11): the `files` row goes (cascading to asset_files) with a `detached` audit entry in one
 * transaction, and its bytes and thumbnail are removed after the commit.
 */
final class RemoveAssetFile
{
    public function handle(User $actor, Asset $asset, File $file): void
    {
        Gate::forUser($actor)->authorize('manageFiles', $asset);

        DB::transaction(function () use ($actor, $asset, $file): void {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageFiles', $asset);
            $file = File::query()->whereKey($file->id)->lockForUpdate()->firstOrFail();
            $kind = $asset->files()->whereKey($file->id)->value('asset_files.kind');
            abort_if($kind === null, 404);

            Audit::record(AuditAction::Detached, $asset, old: [$kind => $file->original_name], actor: $actor);

            $file->deleteWithBytes();
        });
    }
}
