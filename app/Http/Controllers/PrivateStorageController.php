<?php

namespace App\Http\Controllers;

use App\Services\PrivateFileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve files from the private disk with per-record authorization.
 */
class PrivateStorageController extends Controller
{
    public function __invoke(Request $request, string $filepath, PrivateFileAccess $access): StreamedResponse
    {
        $access->authorize($request, $filepath);

        $disk = Storage::disk('private');

        abort_unless($disk->exists($filepath), 404);

        return $disk->download($filepath, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
