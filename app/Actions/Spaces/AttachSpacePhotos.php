<?php

namespace App\Actions\Spaces;

use App\Enums\AuditAction;
use App\Models\File;
use App\Models\Space;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\ConstraintViolation;
use App\Support\Files\FileRules;
use App\Support\Files\FileStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * RF_41 · Attach one or more photos to a space. Space-scoped (★): `managePhotos`. Each photo is a `files` row (with
 * its phone thumbnail) linked through space_photos after the existing ones, and an `attached` audit entry on the
 * space, all in one transaction; if it fails, the stored bytes are removed.
 */
final class AttachSpacePhotos
{
    public const MAX_PER_UPLOAD = 10;

    /**
     * @param  array<int, mixed>  $photos
     * @return Collection<int, File>
     */
    public function handle(User $actor, Space $space, array $photos): Collection
    {
        Gate::forUser($actor)->authorize('managePhotos', $space);

        /** @var array{photos: list<UploadedFile>} $data */
        $data = Validator::make(['photos' => $photos], [
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_PER_UPLOAD],
            'photos.*' => FileRules::image(),
        ], attributes: [
            'photos' => __('spaces.photos.attribute'),
            'photos.*' => __('spaces.photos.attribute'),
        ])->validate();

        $storage = app(FileStorage::class);

        try {
            return DB::transaction(function () use ($actor, $space, $data, $storage): Collection {
                $sortOrder = (int) $space->photos()->max('space_photos.sort_order');

                return collect($data['photos'])->map(function (UploadedFile $upload) use ($actor, $space, $storage, &$sortOrder): File {
                    $file = $storage->store($upload, $actor, "spaces/{$space->id}");
                    $space->photos()->attach($file->id, ['sort_order' => ++$sortOrder]);

                    Audit::record(AuditAction::Attached, $space, new: ['photo' => $file->original_name], actor: $actor);

                    return $file;
                });
            });
        } catch (Throwable $e) {
            $storage->discardWritten();

            throw $e instanceof QueryException ? ConstraintViolation::toValidationException($e, field: 'photos') : $e;
        }
    }
}
