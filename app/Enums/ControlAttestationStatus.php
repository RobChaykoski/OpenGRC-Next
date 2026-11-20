<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ControlAttestationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Attested = 'attested';
    case Declined = 'declined';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Attested => 'Attested',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Attested => 'success',
            self::Declined => 'danger',
            self::Expired => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
