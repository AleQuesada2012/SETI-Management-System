<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Space;
use App\Support\Files\FileResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF_41 · A space's photo, or its phone thumbnail with `?miniatura=1`. The route already checked `view` on the space;
 * a file that is not one of this space's photos answers 404, so no id guessing reaches another owner's files.
 */
class ShowSpacePhotoController extends Controller
{
    public function __invoke(Request $request, Space $space, File $file): StreamedResponse
    {
        abort_unless($space->photos()->whereKey($file->id)->exists(), 404);

        return FileResponse::inline($file, thumbnail: $request->boolean('miniatura'));
    }
}
