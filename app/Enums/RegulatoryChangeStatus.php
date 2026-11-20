<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RegulatoryChangeStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Assessing = 'assessing';
    case Implemented = 'implemented';
    case NoImpact = 'no_impact';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Assessing => 'Assessing',
            self::Implemented => 'Implemented',
            self::NoImpact => 'No impact',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New => 'info',
            self::Assessing => 'warning',
            self::Implemented => 'success',
            self::NoImpact => 'gray',
            self::Closed => 'gray',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function openCases(): array
    {
        return [self::New, self::Assessing];
    }
}
