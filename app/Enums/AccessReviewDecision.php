<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccessReviewDecision: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Attest = 'attest';
    case Revoke = 'revoke';
    case NotApplicable = 'not_applicable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Attest => 'Attest',
            self::Revoke => 'Revoke',
            self::NotApplicable => 'Not applicable',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Attest => 'success',
            self::Revoke => 'danger',
            self::NotApplicable => 'gray',
        };
    }

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
}
