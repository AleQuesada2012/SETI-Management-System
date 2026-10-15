<?php

namespace App\Support\Files;

use App\Models\File;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores uploaded files the way ADR-0007 and Agent-DB.md §11 describe: the bytes on the private
 * `config('filesystems.files_disk')` disk, one `files` row with size, SHA-256 and uploader, and for images their width
 * and height plus a ~400 px WebP thumbnail for phones (turned upright from the photo's EXIF orientation).
 *
 * Call it inside the owner's transaction (RF_41 space photos, RF_20/RF_21 asset files, RF_13 evidence) and link the
 * returned File there. Bytes are written before the transaction commits, so if it fails, call discardWritten():
 *
 *     $storage = app(FileStorage::class);
 *     try {
 *         DB::transaction(fn () => $space->photos()->attach($storage->store($upload, $user, "spaces/{$space->id}")));
 *     } catch (Throwable $e) {
 *         $storage->discardWritten();
 *         throw $e;
 *     }
 */
final class FileStorage
{
    public const THUMBNAIL_WIDTH = 400;

    /** @var array<string, string> mime type => file extension */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    /** @var list<array{disk: string, path: string}> */
    private array $written = [];

    public function store(UploadedFile $upload, User $uploader, string $folder): File
    {
        $disk = (string) config('filesystems.files_disk');
        $mimeType = (string) $upload->getMimeType();
        $extension = self::EXTENSIONS[$mimeType] ?? throw new RuntimeException("Unsupported file type [{$mimeType}].");
        $directory = trim($folder, '/').'/'.now()->format('Y/m');
        $name = (string) Str::uuid7();

        $path = $this->write($disk, "{$directory}/{$name}.{$extension}", (string) file_get_contents($upload->getRealPath()));

        $width = $height = null;
        $thumbnailPath = null;

        if (str_starts_with($mimeType, 'image/') && ($image = $this->uprightImage($upload)) !== null) {
            $width = imagesx($image);
            $height = imagesy($image);
            $thumbnailPath = $this->write($disk, "{$directory}/{$name}.thumb.webp", $this->thumbnail($image));
        }

        return File::query()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($upload->getClientOriginalName(), 250, ''),
            'mime_type' => $mimeType,
            'size_bytes' => $upload->getSize(),
            'sha256' => hash_file('sha256', $upload->getRealPath()),
            'width' => $width,
            'height' => $height,
            'thumbnail_path' => $thumbnailPath,
            'uploaded_by' => $uploader->id,
        ]);
    }

    /** Delete every file written by this instance (after the owner's transaction failed). */
    public function discardWritten(): void
    {
        foreach ($this->written as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        $this->written = [];
    }

    private function write(string $disk, string $path, string $contents): string
    {
        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException("Could not store the uploaded file on disk [{$disk}].");
        }

        $this->written[] = ['disk' => $disk, 'path' => $path];

        return $path;
    }

    /** The image turned upright, or null when GD cannot read it. */
    private function uprightImage(UploadedFile $upload): ?GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($upload->getRealPath()));

        if ($image === false) {
            return null;
        }

        $orientation = 1;

        if ($upload->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($upload->getRealPath());
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        }

        $angle = match ($orientation) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        if ($angle !== 0 && ($rotated = imagerotate($image, $angle, 0)) !== false) {
            return $rotated;
        }

        return $image;
    }

    /** A WebP at most THUMBNAIL_WIDTH wide, keeping the proportions and any transparency. */
    private function thumbnail(GdImage $image): string
    {
        $thumbnail = imagesx($image) > self::THUMBNAIL_WIDTH ? imagescale($image, self::THUMBNAIL_WIDTH) : $image;

        if ($thumbnail === false) {
            $thumbnail = $image;
        }

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);

        ob_start();
        imagewebp($thumbnail, null, 80);

        return (string) ob_get_clean();
    }
}
