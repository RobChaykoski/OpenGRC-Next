<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AccessGrantState: string implements HasColor, HasIcon, HasLabel
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
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
            self::Approved => 'info',
            self::Active => 'success',
            self::Expired => 'gray',
            self::Revoked => 'danger',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Requested => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check',
            self::Active => 'heroicon-o-lock-open',
            self::Expired => 'heroicon-o-calendar-days',
            self::Revoked => 'heroicon-o-no-symbol',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Active], true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }
}
