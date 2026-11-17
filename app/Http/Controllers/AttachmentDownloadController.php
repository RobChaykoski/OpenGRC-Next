<?php

namespace App\Http\Controllers;

use App\Models\FileAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download evidence through a short-lived signed URL.
 *
 * The signature is the authorization: it is only issued to a user who has
 * already been authorized to view the attachment, and it cannot be guessed or
 * reused indefinitely.
 */
class AttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, FileAttachment $fileAttachment): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->can('view', $fileAttachment), 403);

        $disk = Storage::disk(setting('storage.driver', 'private'));

        abort_unless($fileAttachment->file_path !== null && $disk->exists($fileAttachment->file_path), 404);

        return $disk->download(
            $fileAttachment->file_path,
            $fileAttachment->file_name ?: null,
            ['Cache-Control' => 'private, no-store, max-age=0']
        );
    }
}
