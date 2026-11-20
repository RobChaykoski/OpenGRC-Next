<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PrivacyRequestStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case VerificationPending = 'verification_pending';
    case InProgress = 'in_progress';
    case Extended = 'extended';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case Denied = 'denied';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::VerificationPending => 'Verification pending',
            self::InProgress => 'In progress',
            self::Extended => 'Extended',
            self::Overdue => 'Overdue',
            self::Completed => 'Completed',
            self::Denied => 'Denied',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Received, self::InProgress => 'info',
            self::VerificationPending, self::Extended => 'warning',
            self::Overdue => 'danger',
            self::Completed => 'success',
            self::Denied, self::Withdrawn => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::Received,
            self::VerificationPending,
            self::InProgress,
            self::Extended,
            self::Overdue,
        ], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }
}
