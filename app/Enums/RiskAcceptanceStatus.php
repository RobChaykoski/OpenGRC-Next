<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The acceptance state of a residual risk.
 *
 * This is separate from the mitigation strategy "Accept": a mitigation is an
 * action, an acceptance is a dated decision by a named person.
 */
enum RiskAcceptanceStatus: string implements HasColor, HasLabel
{
    case None = 'none';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Not accepted',
            self::Pending => 'Acceptance pending',
            self::Accepted => 'Accepted',
            self::Expired => 'Acceptance expired',
            self::Revoked => 'Acceptance revoked',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::None => 'gray',
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Expired, self::Revoked => 'danger',
        };
    }
}
