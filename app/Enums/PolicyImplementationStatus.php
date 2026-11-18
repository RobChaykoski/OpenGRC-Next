<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Implementation readiness for one policy or document.
 */
enum PolicyImplementationStatus: string implements HasColor, HasLabel
{
    case NotPlanned = 'not_planned';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Implemented = 'implemented';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotPlanned => 'Not Planned',
            self::Planned => 'Planned',
            self::InProgress => 'In Progress',
            self::Implemented => 'Implemented',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NotPlanned => 'gray',
            self::Planned => 'info',
            self::InProgress => 'warning',
            self::Implemented => 'success',
        };
    }

    public function score(): float
    {
        return match ($this) {
            self::NotPlanned => 0.0,
            self::Planned => 1 / 3,
            self::InProgress => 2 / 3,
            self::Implemented => 1.0,
        };
    }

    public static function fromLabel(?string $label): self
    {
        $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', (string) $label)));

        return match ($key) {
            'planned' => self::Planned,
            'in progress' => self::InProgress,
            'implemented' => self::Implemented,
            default => self::NotPlanned,
        };
    }
}
