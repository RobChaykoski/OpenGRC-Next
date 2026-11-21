<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccessRuleScope: string implements HasLabel
{
    case Access = 'access';
    case Registration = 'registration';

    public function getLabel(): string
    {
        return match ($this) {
            self::Access => __('access-rules.scopes.access'),
            self::Registration => __('access-rules.scopes.registration'),
        };
    }
}
