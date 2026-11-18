<?php

namespace App\Exceptions;

use App\Services\Dlp\DlpScanResult;
use RuntimeException;

/**
 * Thrown when content contains data that must not leave the application
 * boundary (an AI prompt) or must not be stored without review (an upload).
 */
class DlpBlockedException extends RuntimeException
{
    public function __construct(
        public readonly DlpScanResult $result,
        ?string $message = null,
    ) {
        parent::__construct($message ?? 'The content contains data blocked by the data loss prevention policy.');
    }
}
