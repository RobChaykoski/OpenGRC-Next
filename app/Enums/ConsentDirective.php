<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A patient- or data-subject directive attached to a protected resource.
 *
 * This is a directive, not a deny bit. A lockbox changes who must justify the
 * access and who reviews it afterwards; whether it may be overridden for
 * emergency care is a legal question, recorded with the decision.
 */
enum ConsentDirective: string implements HasColor, HasLabel
{
    case None = 'none';
    case Lockbox = 'lockbox';
    case OptOut = 'opt_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'No directive',
            self::Lockbox => 'Lockbox',
            self::OptOut => 'Opt-out',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::None => 'gray',
            self::Lockbox => 'warning',
            self::OptOut => 'warning',
        };
    }

    public function restrictsDisclosure(): bool
    {
        return $this !== self::None;
    }
}
