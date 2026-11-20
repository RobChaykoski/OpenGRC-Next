<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The privacy regime that sets the response clock and request vocabulary.
 *
 * The day counts are working defaults, not legal advice. Confirm the current
 * statutory period and any extension rules with counsel for each jurisdiction.
 */
enum PrivacyRegime: string implements HasColor, HasLabel
{
    case Gdpr = 'gdpr';
    case Ccpa = 'ccpa_cpra';
    case Pipeda = 'pipeda';
    case Ferpa = 'ferpa';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gdpr => 'GDPR',
            self::Ccpa => 'CCPA / CPRA',
            self::Pipeda => 'PIPEDA',
            self::Ferpa => 'FERPA',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Gdpr => 'info',
            self::Ccpa => 'warning',
            self::Pipeda => 'success',
            self::Ferpa => 'gray',
            self::Other => 'gray',
        };
    }

    /**
     * Base response period in days. GDPR is one calendar month, approximated as
     * 30 days for a working default; counsel should confirm the exact method.
     */
    public function responseDays(): int
    {
        return match ($this) {
            self::Gdpr => 30,
            self::Ccpa => 45,
            self::Pipeda => 30,
            self::Ferpa => 45,
            self::Other => 30,
        };
    }

    /**
     * Additional days available through a recorded extension.
     */
    public function extensionDays(): int
    {
        return match ($this) {
            self::Gdpr => 60,
            self::Ccpa => 45,
            self::Pipeda => 30,
            self::Ferpa => 30,
            self::Other => 30,
        };
    }

    public function citation(): string
    {
        return match ($this) {
            self::Gdpr => 'GDPR Art. 12(3): one month, extendable by two further months with notice.',
            self::Ccpa => 'CCPA: 45 calendar days, extendable to 90 days in total with notice.',
            self::Pipeda => 'PIPEDA s.8(3): 30 days, with a permitted extension and notice.',
            self::Ferpa => 'FERPA: 45 days is a common institutional policy; confirm your own procedure.',
            self::Other => 'No statutory clock is assumed. Set the due date from your own policy.',
        };
    }
}
