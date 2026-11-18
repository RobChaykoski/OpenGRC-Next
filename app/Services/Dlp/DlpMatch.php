<?php

namespace App\Services\Dlp;

/**
 * One category of sensitive content found in a scan.
 *
 * The matched text is deliberately not retained. The scanner reports the type,
 * label, severity, and count so a finding can be reviewed without copying the
 * sensitive value into logs or the database.
 */
final readonly class DlpMatch
{
    public function __construct(
        public string $type,
        public string $label,
        public string $severity,
        public int $count = 1,
    ) {}
}
