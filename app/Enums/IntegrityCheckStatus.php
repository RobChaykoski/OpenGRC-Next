<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Outcome of one integrity verification run.
 *
 * Pass means the chain verified; Warn means it verified but something needs
 * attention (warnings, a missing table, or the ledger is not installed yet);
 * Fail means the chain is broken or an evidence hash no longer matches.
 */
enum IntegrityCheckStatus: string implements HasColor, HasLabel
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Warn => 'Warning',
            self::Fail => 'Fail',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pass => 'success',
            self::Warn => 'warning',
            self::Fail => 'danger',
        };
    }

    /**
     * Higher is worse. Used to pick the status a dashboard should show.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pass => 0,
            self::Warn => 1,
            self::Fail => 2,
        };
    }
}
