<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The verdict of a measurement against a control.
 *
 * A missing measurement is Unknown, never Effective. An unknown is visible on
 * purpose: a silent gap in coverage must not look like compliance.
 */
enum ControlStatus: string implements HasColor, HasLabel
{
    case Effective = 'effective';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Effective => 'Effective',
            self::Failed => 'Failed',
            self::Unknown => 'Unknown',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Effective => 'success',
            self::Failed => 'danger',
            self::Unknown => 'gray',
        };
    }
}
