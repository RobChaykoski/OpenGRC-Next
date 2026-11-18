<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Effectiveness readiness for one policy or document.
 *
 * Design effective means the control design was tested; operating effective
 * means it was observed working. They are different claims.
 */
enum PolicyEffectivenessStatus: string implements HasColor, HasLabel
{
    case NotTested = 'not_tested';
    case DesignEffective = 'design_effective';
    case NeedsImprovement = 'needs_improvement';
    case OperatingEffective = 'operating_effective';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotTested => 'Not Tested',
            self::DesignEffective => 'Design Effective',
            self::NeedsImprovement => 'Needs Improvement',
            self::OperatingEffective => 'Operating Effective',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NotTested => 'gray',
            self::DesignEffective => 'info',
            self::NeedsImprovement => 'danger',
            self::OperatingEffective => 'success',
        };
    }

    public function score(): float
    {
        return match ($this) {
            self::NotTested => 0.0,
            self::DesignEffective => 1 / 3,
            self::NeedsImprovement => 2 / 3,
            self::OperatingEffective => 1.0,
        };
    }

    public static function fromLabel(?string $label): self
    {
        $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', (string) $label)));

        return match ($key) {
            'design effective' => self::DesignEffective,
            'needs improvement' => self::NeedsImprovement,
            'operating effective' => self::OperatingEffective,
            default => self::NotTested,
        };
    }
}
