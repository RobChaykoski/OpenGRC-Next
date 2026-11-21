<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccessRuleEffect: string implements HasColor, HasLabel
{
    case Allow = 'allow';
    case Deny = 'deny';

    public function getLabel(): string
    {
        return match ($this) {
            self::Allow => __('access-rules.effects.allow'),
            self::Deny => __('access-rules.effects.deny'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Allow => 'success',
            self::Deny => 'danger',
        };
    }
}
