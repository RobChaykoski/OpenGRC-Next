<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The lifecycle of an assistant proposal (ADR 0055).
 *
 * Proposed -> Approved -> Applied -> RolledBack, with Rejected and Failed as
 * terminal states. Nothing moves without a human decision recorded against it.
 */
enum AssistantProposalState: string implements HasColor, HasLabel
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::Approved => 'Approved',
            self::Applied => 'Applied',
            self::Rejected => 'Rejected',
            self::Failed => 'Failed',
            self::RolledBack => 'Rolled back',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Proposed => 'warning',
            self::Approved => 'info',
            self::Applied => 'success',
            self::Rejected => 'danger',
            self::Failed => 'danger',
            self::RolledBack => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Proposed, self::Approved], true);
    }
}
