<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentClassification: string implements HasColor, HasLabel
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Internal => 'Internal',
            self::Confidential => 'Confidential',
            self::Restricted => 'Restricted',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Public => 'success',
            self::Internal => 'gray',
            self::Confidential => 'warning',
            self::Restricted => 'danger',
        };
    }

    public function requiresWriter(): bool
    {
        return $this === self::Confidential || $this === self::Restricted;
    }
}
