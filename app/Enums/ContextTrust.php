<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How much the enforcement point trusts the session at decision time.
 *
 * High means managed device, expected network zone and a normal location.
 * Low means the request is outside the trusted boundary. The evaluator never
 * upgrades a trust level on its own.
 */
enum ContextTrust: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Low => 'danger',
            self::Medium => 'warning',
            self::High => 'success',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }
}
