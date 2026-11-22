<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What the enforcement technology does when a policy matches (ADR 0053).
 *
 * The action is the policy's intent. OpenGRC records it; the scanning engine
 * enforces it. Monitor is not a pass: the finding still needs a review.
 */
enum DlpPolicyAction: string implements HasColor, HasLabel
{
    case Monitor = 'monitor';
    case Alert = 'alert';
    case Block = 'block';
    case Quarantine = 'quarantine';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monitor => 'Monitor',
            self::Alert => 'Alert',
            self::Block => 'Block',
            self::Quarantine => 'Quarantine',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Monitor => 'gray',
            self::Alert => 'info',
            self::Block => 'danger',
            self::Quarantine => 'warning',
        };
    }
}
