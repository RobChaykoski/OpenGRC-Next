<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The outcome of the privacy review that a break-the-glass event creates.
 *
 * Pending is a real state with a due date. An alert that nobody reviews is the
 * failure mode this enum exists to make visible.
 */
enum PrivacyReviewOutcome: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Justified = 'justified';
    case NotJustified = 'not_justified';
    case Escalated = 'escalated';
    case NotApplicable = 'not_applicable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Justified => 'Justified',
            self::NotJustified => 'Not justified',
            self::Escalated => 'Escalated',
            self::NotApplicable => 'No review required',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Justified => 'success',
            self::NotJustified => 'danger',
            self::Escalated => 'danger',
            self::NotApplicable => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
