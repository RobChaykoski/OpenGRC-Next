<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What the enforcement point must do with the request.
 *
 * `time_boxed` is an allow with a clock and a review obligation. `deny` is the
 * default when nothing matches: the evaluator never returns allow because it
 * could not decide.
 */
enum AccessAction: string implements HasColor, HasIcon, HasLabel
{
    case Allow = 'allow';
    case AllowAudited = 'allow_audited';
    case Mask = 'mask';
    case Filter = 'filter';
    case StepUp = 'step_up';
    case TimeBoxed = 'time_boxed';
    case Deny = 'deny';

    public function getLabel(): string
    {
        return match ($this) {
            self::Allow => 'Allow',
            self::AllowAudited => 'Allow, audited',
            self::Mask => 'Mask columns',
            self::Filter => 'Filter rows',
            self::StepUp => 'Require step-up',
            self::TimeBoxed => 'Time-boxed allow',
            self::Deny => 'Deny',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Allow => 'success',
            self::AllowAudited => 'info',
            self::TimeBoxed => 'warning',
            self::Mask, self::Filter, self::StepUp => 'warning',
            self::Deny => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Allow => 'heroicon-o-check-circle',
            self::AllowAudited => 'heroicon-o-eye',
            self::Mask => 'heroicon-o-eye-slash',
            self::Filter => 'heroicon-o-funnel',
            self::StepUp => 'heroicon-o-shield-check',
            self::TimeBoxed => 'heroicon-o-clock',
            self::Deny => 'heroicon-o-no-symbol',
        };
    }

    public function isPermissive(): bool
    {
        return in_array($this, [self::Allow, self::AllowAudited, self::TimeBoxed], true);
    }

    /**
     * Conflict weight. The most restrictive action wins when two policies at
     * the same priority disagree.
     */
    public function restrictiveness(): int
    {
        return match ($this) {
            self::Allow => 10,
            self::AllowAudited => 20,
            self::TimeBoxed => 30,
            self::StepUp => 40,
            self::Filter => 50,
            self::Mask => 60,
            self::Deny => 70,
        };
    }

    public function requiresTimeBox(): bool
    {
        return $this === self::TimeBoxed;
    }
}
