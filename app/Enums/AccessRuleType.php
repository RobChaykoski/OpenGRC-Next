<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccessRuleType: string implements HasLabel
{
    case Ip = 'ip';
    case EmailDomain = 'email_domain';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ip => __('access-rules.types.ip'),
            self::EmailDomain => __('access-rules.types.email_domain'),
        };
    }
}
