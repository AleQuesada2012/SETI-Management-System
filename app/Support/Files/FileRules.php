<?php

namespace App\Support\Files;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Validation rules for uploads, the same limits the `files` table enforces (Agent-DB.md §11): images are JPEG, PNG,
 * WebP or GIF up to 10 MB; documents are PDF up to 20 MB or images up to 10 MB. Laravel checks them first so users get a
 * Spanish message; the database stays the final judge (files_mime_type_check, files_size_check).
 *
 *     'photos.*' => FileRules::image(),      // RF_41, RF_21
 *     'documents.*' => FileRules::document(), // RF_20
 */
final class FileRules
{
    /** @var list<string> */
    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** @var list<string> */
    public const DOCUMENT_MIME_TYPES = ['application/pdf', ...self::IMAGE_MIME_TYPES];

    public const IMAGE_MAX_KILOBYTES = 10 * 1024;

    public const DOCUMENT_MAX_KILOBYTES = 20 * 1024;

    /**
     * @return list<string>
     */
    public static function image(): array
    {
        return ['file', 'mimetypes:'.implode(',', self::IMAGE_MIME_TYPES), 'max:'.self::IMAGE_MAX_KILOBYTES];
    }

    /**
     * @return list<string|Closure>
     */
    public static function document(): array
    {
        return ['file', 'mimetypes:'.implode(',', self::DOCUMENT_MIME_TYPES), 'max:'.self::DOCUMENT_MAX_KILOBYTES,
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof UploadedFile && in_array($value->getMimeType(), self::IMAGE_MIME_TYPES, true)
                    && $value->getSize() > self::IMAGE_MAX_KILOBYTES * 1024) {
                    $fail('validation.max.file')->translate(['attribute' => $attribute, 'max' => self::IMAGE_MAX_KILOBYTES]);
                }
            },
        ];
    }
}
