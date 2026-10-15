<?php

namespace App\Actions\Spaces;

use App\Enums\AuditAction;
use App\Models\File;
use App\Models\Space;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * RF_41 · Remove a photo from a space. Space-scoped (★): `managePhotos`. Attachments are hard-deleted (Agent-DB.md §11):
 * the `files` row goes (cascading to space_photos) with a `detached` audit entry in one transaction, and its bytes
 * and thumbnail are removed after the commit.
 */
final class RemoveSpacePhoto
{
    public function handle(User $actor, Space $space, File $photo): void
    {
        Gate::forUser($actor)->authorize('managePhotos', $space);

        abort_unless($space->photos()->whereKey($photo->id)->exists(), 404);

        DB::transaction(function () use ($actor, $space, $photo): void {
            Audit::record(AuditAction::Detached, $space, old: ['photo' => $photo->original_name], actor: $actor);

            $photo->deleteWithBytes();
        });
    }
}
