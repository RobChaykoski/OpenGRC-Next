<?php

namespace App\Services\Dlp;

interface DlpScanner
{
    /**
     * @return array<int, DlpMatch>
     */
    public function scan(string $text): array;

    public function name(): string;
}
