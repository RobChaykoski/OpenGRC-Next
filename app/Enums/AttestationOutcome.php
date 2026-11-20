<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The opinion or compliance conclusion in the report.
 *
 * The label is deliberately generic: an unqualified SOC opinion and a PCI AOC
 * "compliant" result are not the same assessment, but both are positive
 * conclusions. The report type supplies the meaning.
 */
enum AttestationOutcome: string implements HasColor, HasLabel
{
    case Unqualified = 'unqualified';
    case Qualified = 'qualified';
    case Adverse = 'adverse';
    case Disclaimer = 'disclaimer';
    case Compliant = 'compliant';
    case NonCompliant = 'non_compliant';
    case NotAssessed = 'not_assessed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unqualified => 'Unqualified',
            self::Qualified => 'Qualified',
            self::Adverse => 'Adverse',
            self::Disclaimer => 'Disclaimer',
            self::Compliant => 'Compliant',
            self::NonCompliant => 'Non-compliant',
            self::NotAssessed => 'Not assessed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unqualified, self::Compliant => 'success',
            self::Qualified => 'warning',
            self::Adverse, self::NonCompliant => 'danger',
            self::Disclaimer, self::NotAssessed => 'gray',
        };
    }

    public function isPositive(): bool
    {
        return in_array($this, [self::Unqualified, self::Compliant], true);
    }
}
