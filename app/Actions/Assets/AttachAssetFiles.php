<?php

namespace App\Actions\Assets;

use App\Enums\AssetFileKind;
use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\File;
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
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RF_20 · attach documents, RF_21 · attach photos to an asset, on the upload mechanism of RF_41 (App\Support\Files).
 * Space-scoped (★): `manageFiles`. Each file is a `files` row linked through asset_files with its kind, after the
 * asset's existing files of that kind, plus an `attached` audit entry on the asset, in one transaction; if it fails,
 * the stored bytes are removed. A decommissioned asset takes no more files (Asset::isReadOnly).
 */
final class AttachAssetFiles
{
    public const MAX_PER_UPLOAD = 10;

    /**
     * @param  array<int, mixed>  $uploads
     * @return Collection<int, File>
     */
    public function handle(User $actor, Asset $asset, AssetFileKind $kind, array $uploads): Collection
    {
        Gate::forUser($actor)->authorize('manageFiles', $asset);

        $attribute = __("assets.files.{$kind->value}.attribute");

        /** @var array{files: list<UploadedFile>} $data */
        $data = Validator::make(['files' => $uploads], [
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_PER_UPLOAD],
            'files.*' => $kind === AssetFileKind::Photo ? FileRules::image() : FileRules::document(),
        ], attributes: ['files' => $attribute, 'files.*' => $attribute])->validate();

        $storage = app(FileStorage::class);

        try {
            return DB::transaction(function () use ($actor, $asset, $kind, $data, $storage): Collection {
                $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('manageFiles', $asset);

                if ($asset->isReadOnly()) {
                    throw ValidationException::withMessages(['files' => __('assets.read_only')]);
                }

                $sortOrder = (int) $asset->files()->wherePivot('kind', $kind->value)->max('asset_files.sort_order');

                return collect($data['files'])->map(function (UploadedFile $upload) use ($actor, $asset, $kind, $storage, &$sortOrder): File {
                    $file = $storage->store($upload, $actor, "assets/{$asset->id}/{$kind->value}s");
                    $asset->files()->attach($file->id, ['kind' => $kind->value, 'sort_order' => ++$sortOrder]);

                    Audit::record(AuditAction::Attached, $asset, new: [$kind->value => $file->original_name], actor: $actor);

                    return $file;
                });
            });
        } catch (Throwable $e) {
            $storage->discardWritten();

            throw $e instanceof QueryException ? ConstraintViolation::toValidationException($e, field: 'files') : $e;
        }
    }
}
