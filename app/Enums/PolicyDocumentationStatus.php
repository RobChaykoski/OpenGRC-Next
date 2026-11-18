<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Documentation readiness for one policy or document.
 */
enum PolicyDocumentationStatus: string implements HasColor, HasLabel
{
    case NotStarted = 'not_started';
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Draft => 'Draft',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::Draft => 'info',
            self::InReview => 'warning',
            self::Approved => 'success',
        };
    }

    /**
     * Fraction of the documentation dimension completed. Used for maturity.
     */
    public function score(): float
    {
        return match ($this) {
            self::NotStarted => 0.0,
            self::Draft => 1 / 3,
            self::InReview => 2 / 3,
            self::Approved => 1.0,
        };
    }

    public static function fromLabel(?string $label): self
    {
        $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', (string) $label)));

        return match ($key) {
            'draft' => self::Draft,
            'in review' => self::InReview,
            'approved' => self::Approved,
            default => self::NotStarted,
        };
    }
}
