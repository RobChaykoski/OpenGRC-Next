<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NonHumanIdentityStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case RotationDue = 'rotation_due';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case PendingReview = 'pending_review';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::RotationDue => 'Rotation due',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
            self::PendingReview => 'Pending review',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::RotationDue, self::PendingReview => 'warning',
            self::Expired, self::Revoked => 'danger',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::RotationDue, self::PendingReview], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isActive();
    }
}
