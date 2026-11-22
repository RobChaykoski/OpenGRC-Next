<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where data can leave the organisation (ADR 0053).
 *
 * Channels are a controlled vocabulary, not free text: a report that groups by
 * channel is only useful if the names are stable.
 */
enum DlpChannel: string implements HasLabel
{
    case Email = 'email';
    case Endpoint = 'endpoint';
    case Cloud = 'cloud';
    case Web = 'web';
    case Ai = 'ai';
    case Upload = 'upload';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Endpoint => 'Endpoint',
            self::Cloud => 'Cloud storage',
            self::Web => 'Web',
            self::Ai => 'AI prompt',
            self::Upload => 'Upload',
        };
    }
}
