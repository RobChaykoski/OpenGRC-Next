<?php

namespace App\Services;

use App\Models\FileAttachment;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Authorization for files served out of private storage.
 *
 * Authentication alone is not authorization: an authenticated user who guesses
 * a path must not be able to read another record's evidence. Every request is
 * checked against the record that owns the file, with a permission fallback for
 * paths that are not attached to a modelled record (rich text images).
 */
class PrivateFileAccess
{
    /**
     * Directories that may be served. Anything else is rejected outright.
     *
     * @var array<int, string>
     */
    public const PREFIXES = [
        'attachments',
        'policies',
        'data-request-attachments',
        'survey-attachments',
        'application-logos',
        'assets',
        'trust-center',
        'certifications',
    ];

    /**
     * Reject unsafe or unknown paths.
     */
    public function assertSafePath(string $path): void
    {
        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || str_starts_with($path, '/')) {
            abort(403, 'Invalid file path.');
        }

        $prefix = explode('/', $path)[0];

        if (! in_array($prefix, self::PREFIXES, true)) {
            abort(403, 'Invalid file path.');
        }
    }

    /**
     * Authorize the request for the given path.
     */
    public function authorize(Request $request, string $path): void
    {
        $this->assertSafePath($path);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return;
        }

        $attachment = FileAttachment::query()->where('file_path', $path)->first();

        if ($attachment) {
            abort_unless($user->can('view', $attachment), 403);

            return;
        }

        $policy = Policy::query()->where('document_path', $path)->first();

        if ($policy) {
            abort_unless($user->can('view', $policy), 403);

            return;
        }

        // Paths without a modelled owner (editor images, generated exports) are
        // gated on the relevant list permission rather than being public.
        if ($user->can('viewAny', FileAttachment::class) || $user->can('viewAny', Policy::class)) {
            return;
        }

        abort(403);
    }
}
