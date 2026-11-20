<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PrivacyRequestType: string implements HasColor, HasLabel
{
    case Access = 'access';
    case Deletion = 'deletion';
    case Correction = 'correction';
    case Portability = 'portability';
    case Restriction = 'restriction';
    case Objection = 'objection';
    case OptOutSaleShare = 'opt_out_sale_share';
    case LimitSensitivePi = 'limit_sensitive_pi';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Access => 'Access',
            self::Deletion => 'Deletion',
            self::Correction => 'Correction',
            self::Portability => 'Portability',
            self::Restriction => 'Restriction',
            self::Objection => 'Objection',
            self::OptOutSaleShare => 'Opt out of sale/share',
            self::LimitSensitivePi => 'Limit sensitive PI',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Access, self::Portability => 'info',
            self::Deletion, self::OptOutSaleShare, self::LimitSensitivePi => 'warning',
            self::Correction, self::Restriction, self::Objection => 'gray',
            self::Other => 'gray',
        };
    }
}
