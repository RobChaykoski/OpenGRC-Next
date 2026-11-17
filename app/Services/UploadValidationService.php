<?php

namespace App\Services;

use App\Support\UploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Server-side upload validation.
 *
 * Filament validates in the browser and again on upload, but "on upload" trusts
 * the client-provided MIME type in some paths. This service inspects the file
 * itself and rejects mismatches between the extension, the claimed type, and the
 * detected content.
 */
class UploadValidationService
{
    /**
     * Executable and active-content extensions that must never be accepted as
     * evidence, regardless of the declared MIME type.
     *
     * @var array<int, string>
     */
    protected array $dangerousExtensions = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'pht',
        'js', 'mjs', 'html', 'htm', 'svg', 'xml', 'xhtml',
        'exe', 'dll', 'com', 'bat', 'cmd', 'ps1', 'sh', 'bash',
        'jar', 'msi', 'scr', 'vbs', 'jsp', 'asp', 'aspx',
    ];

    /**
     * Validate an uploaded file against a rule group.
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile $file, string $group = UploadRules::EVIDENCE): void
    {
        $rules = UploadRules::group($group);
        $name = (string) $file->getClientOriginalName();
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! $file->isValid()) {
            $this->fail('The upload did not complete. Try again.');
        }

        if ($extension === '' || in_array($extension, $this->dangerousExtensions, true)) {
            $this->fail('This file type cannot be uploaded.');
        }

        if (! in_array($extension, $rules['extensions'], true)) {
            $this->fail('Allowed file types: '.implode(', ', $rules['extensions']).'.');
        }

        // Reject names that try to smuggle a second extension or a path.
        if (preg_match('/\.(?:'.implode('|', $this->dangerousExtensions).')$/i', $name)) {
            $this->fail('This file type cannot be uploaded.');
        }

        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            $this->fail('The file name is not valid.');
        }

        $size = (int) $file->getSize();

        if ($size <= 0 || $size > $rules['max_kb'] * 1024) {
            $this->fail('The file must be larger than 0 bytes and no more than '.$rules['max_kb'].' KB.');
        }

        $detected = (string) $file->getMimeType();

        if (! in_array($detected, $rules['mimes'], true)) {
            $this->fail('The file content does not match an allowed type (detected: '.$detected.').');
        }

        // The extension and the bytes must agree. A text file renamed to .pdf is
        // allowed by the extension check above and rejected here.
        if (! in_array($detected, $this->mimesForExtension($extension), true)) {
            $this->fail('The file content does not match its extension.');
        }
    }

    /**
     * MIME types that may legitimately carry a given extension.
     *
     * @return array<int, string>
     */
    protected function mimesForExtension(string $extension): array
    {
        return match ($extension) {
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg', 'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'txt' => ['text/plain'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            default => [],
        };
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $message): void
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
