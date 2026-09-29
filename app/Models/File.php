<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Metadata of a stored file; the bytes live on a Laravel Storage disk (ADR-0007). Each file belongs to
 * exactly one owner through one link table (asset_files, space_photos, asset_event_evidence,
 * report_generations.file_id). Not audited itself: owners log `attached` / `detached`.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int|null $width
 * @property int|null $height
 * @property string|null $thumbnail_path
 * @property int|null $uploaded_by
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'width', 'height', 'thumbnail_path', 'uploaded_by'])]
class File extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** Delete the row now (cascades to its link row) and the bytes once the transaction commits. */
    public function deleteWithBytes(): void
    {
        $disk = $this->disk;
        $paths = array_filter([$this->path, $this->thumbnail_path]);

        $this->delete();

        DB::afterCommit(fn () => Storage::disk($disk)->delete($paths));
    }
}
