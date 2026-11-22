<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The lifecycle of a DLP suppression (ADR 0053).
 *
 * A suppression is an acceptance with an expiry, not a switch that hides a
 * finding. It is requested, approved by someone other than the requester, and
 * expires by default.
 */
enum DlpSuppressionState: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Active => 'success',
            self::Expired => 'gray',
            self::Revoked => 'danger',
            self::Rejected => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Active], true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }
}
