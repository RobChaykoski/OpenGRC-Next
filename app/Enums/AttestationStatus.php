<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The validity of the report in the register.
 *
 * Expiry is enforced by a scheduled command for reports with an explicit
 * expires_at. A report with no expiry stays current until it is superseded or
 * withdrawn; its period_end and next_review_at still make the age visible.
 */
enum AttestationStatus: string implements HasColor, HasLabel
{
    case Current = 'current';
    case Expired = 'expired';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::Expired => 'Expired',
            self::Superseded => 'Superseded',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Current => 'success',
            self::Expired => 'danger',
            self::Superseded => 'gray',
            self::Withdrawn => 'danger',
        };
    }
}
