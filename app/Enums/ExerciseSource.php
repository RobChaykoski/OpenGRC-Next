<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExerciseSource: string implements HasLabel
{
    case CisaCtep = 'cisa_ctep';
    case Internal = 'internal';
    case Vendor = 'vendor';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::CisaCtep => 'CISA CTEP',
            self::Internal => 'Internal',
            self::Vendor => 'Vendor',
            self::Other => 'Other',
        };
    }
}
