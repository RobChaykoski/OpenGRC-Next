<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of a finding.
 *
 * Resolved means the issue is fixed. Accepted means the organisation has
 * decided to live with the risk; it is not the same state and must stay
 * distinguishable in reporting.
 */
enum FindingStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Accepted = 'accepted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Accepted => 'Accepted risk',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'danger',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Accepted => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::InProgress;
    }
}
