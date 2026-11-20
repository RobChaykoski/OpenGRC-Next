<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GuidanceStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Adopted = 'adopted';
    case NotApplicable = 'not_applicable';
    case Superseded = 'superseded';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::UnderReview => 'Under review',
            self::Adopted => 'Adopted',
            self::NotApplicable => 'Not applicable',
            self::Superseded => 'Superseded',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New => 'info',
            self::UnderReview => 'warning',
            self::Adopted => 'success',
            self::NotApplicable, self::Superseded => 'gray',
        };
    }
}
