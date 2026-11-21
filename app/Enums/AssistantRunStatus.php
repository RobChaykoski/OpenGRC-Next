<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The outcome of one assistant run.
 *
 * "Facts only" is a normal outcome, not an error: retrieval worked and the AI
 * narrative was skipped because no provider is configured or the provider
 * failed. The facts remain usable and the run is still recorded.
 */
enum AssistantRunStatus: string implements HasColor, HasLabel
{
    case Answered = 'answered';
    case FactsOnly = 'facts_only';
    case BlockedDlp = 'blocked_dlp';
    case QuotaExceeded = 'quota_exceeded';
    case Unsupported = 'unsupported';
    case NotPermitted = 'not_permitted';
    case NeedsInput = 'needs_input';
    case Error = 'error';

    public function getLabel(): string
    {
        return __('assistant.statuses.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Answered => 'success',
            self::FactsOnly => 'info',
            self::BlockedDlp, self::QuotaExceeded, self::Error => 'danger',
            self::Unsupported, self::NeedsInput => 'warning',
            self::NotPermitted => 'gray',
        };
    }
}
