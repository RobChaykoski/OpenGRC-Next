<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A human review outcome for a DLP finding.
 *
 * A false positive is a finding the detector should not have raised. Benign is
 * a true match that the programme has decided is acceptable in context. The
 * distinction matters: only false positives belong in the detector-quality rate.
 */
enum DlpDisposition: string implements HasColor, HasLabel
{
    case TruePositive = 'true_positive';
    case FalsePositive = 'false_positive';
    case Benign = 'benign';
    case Undetermined = 'undetermined';

    public function getLabel(): string
    {
        return match ($this) {
            self::TruePositive => 'True positive',
            self::FalsePositive => 'False positive',
            self::Benign => 'Benign / accepted',
            self::Undetermined => 'Undetermined',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TruePositive => 'danger',
            self::FalsePositive => 'warning',
            self::Benign => 'info',
            self::Undetermined => 'gray',
        };
    }

    public function countsAsFalsePositive(): bool
    {
        return $this === self::FalsePositive;
    }
}
