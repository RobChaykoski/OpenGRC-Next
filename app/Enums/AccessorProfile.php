<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who is asking. The profile is asserted by the enforcement point, not guessed
 * by OpenGRC, and it is a property of the request rather than of the account.
 */
enum AccessorProfile: string implements HasLabel
{
    case Standard = 'standard';
    case Privileged = 'privileged';
    case Executive = 'executive';
    case Machine = 'machine';
    case Emergency = 'emergency';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Privileged => 'Privileged',
            self::Executive => 'Executive',
            self::Machine => 'Machine',
            self::Emergency => 'Emergency responder',
        };
    }

    /**
     * Profiles that may hold a break-the-glass grant. A machine identity and a
     * standard account cannot declare an emergency.
     */
    public function canBreakGlass(): bool
    {
        return $this === self::Emergency || $this === self::Privileged;
    }

    public function isHighProfile(): bool
    {
        return in_array($this, [self::Privileged, self::Executive, self::Emergency], true);
    }
}
