<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\File;
use App\Support\Files\FileResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF_20, RF_21 · An asset's document or photo, or a photo's phone thumbnail with `?miniatura=1`; RF_13 · the evidence of
 * one of the asset's damage events. The route already checked `view` on the asset; a file that belongs to neither
 * answers 404.
 */
class ShowAssetFileController extends Controller
{
    public function __invoke(Request $request, Asset $asset, File $file): StreamedResponse
    {
        abort_unless(
            $asset->files()->whereKey($file->id)->exists()
                || $asset->events()->whereHas('evidence', fn (Builder $evidence) => $evidence->whereKey($file->id))->exists(),
            404,
        );

        return FileResponse::inline($file, thumbnail: $request->boolean('miniatura'));
    }
}
