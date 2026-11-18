<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a finding came from. Automated findings are deduplicated by rule and
 * asset; manual findings are raised by a person and are never closed by a
 * passing check.
 */
enum FindingSource: string implements HasLabel
{
    case Manual = 'manual';
    case Automated = 'automated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Automated => 'Automated check',
        };
    }
}
