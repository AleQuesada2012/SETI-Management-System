<?php

namespace App\Support\Files;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a stored file to someone the route has already authorized (ADR-0007: files are private and only reach the
 * browser through an authorized route; the URL carries ids, never a storage path). Images and PDFs open in the
 * browser; `thumbnail` serves the phone-size WebP when the file has one.
 */
final class FileResponse
{
    public static function inline(File $file, bool $thumbnail = false): StreamedResponse
    {
        $path = $thumbnail && $file->thumbnail_path !== null ? $file->thumbnail_path : $file->path;

        abort_unless(Storage::disk($file->disk)->exists($path), 404);

        return Storage::disk($file->disk)->response($path, $file->original_name, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
