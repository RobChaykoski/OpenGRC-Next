<?php

namespace App\Support;

use App\Services\UploadValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for upload constraints.
 *
 * Filament file fields and the server-side validation service both read these
 * values, so the accept list in the browser cannot drift from what the server
 * actually permits.
 */
final class UploadRules
{
    public const DOCUMENT = 'document';

    public const EVIDENCE = 'evidence';

    public const IMAGE = 'image';

    public const IMPORT = 'import';

    /**
     * @return array{extensions: array<int, string>, mimes: array<int, string>, max_kb: int}
     */
    public static function group(string $group): array
    {
        return match ($group) {
            self::IMAGE => [
                'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
                'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
                'max_kb' => 2048,
            ],
            self::IMPORT => [
                'extensions' => ['csv'],
                'mimes' => ['text/csv', 'text/plain', 'application/csv'],
                'max_kb' => 20480,
            ],
            self::EVIDENCE => [
                'extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'csv', 'txt', 'doc', 'docx', 'xlsx', 'zip'],
                'mimes' => [
                    'application/pdf',
                    'image/png',
                    'image/jpeg',
                    'image/webp',
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip',
                    'application/x-zip-compressed',
                ],
                'max_kb' => 20480,
            ],
            default => [
                'extensions' => ['pdf', 'doc', 'docx', 'xlsx', 'csv', 'txt'],
                'mimes' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'text/plain',
                    'application/csv',
                ],
                'max_kb' => 10240,
            ],
        };
    }

    /**
     * MIME types for Filament's acceptedFileTypes().
     *
     * @return array<int, string>
     */
    public static function mimes(string $group): array
    {
        return self::group($group)['mimes'];
    }

    /**
     * Max size in kilobytes for Filament's maxSize().
     */
    public static function maxKb(string $group): int
    {
        return self::group($group)['max_kb'];
    }

    /**
     * Extensions accepted for a group.
     *
     * @return array<int, string>
     */
    public static function extensions(string $group): array
    {
        return self::group($group)['extensions'];
    }

    /**
     * A Laravel validation rule that re-checks the file server-side.
     *
     * Filament validates in the browser and on upload, but this inspects the
     * bytes and the filename together, which is what catches an extension swap.
     */
    public static function validationRule(string $group): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($group): void {
            $files = is_array($value) ? $value : [$value];

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                try {
                    app(UploadValidationService::class)->validate($file, $group);
                } catch (ValidationException $e) {
                    $fail($e->validator->errors()->first('file') ?: 'The uploaded file is not allowed.');
                }
            }
        };
    }
}
