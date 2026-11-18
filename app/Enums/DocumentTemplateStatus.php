<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentTemplateStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Retired => 'Retired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Approved => 'success',
            self::Retired => 'danger',
        };
    }
}
