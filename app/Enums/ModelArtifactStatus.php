<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The approval state of a model artifact (ADR 0059).
 *
 * Registration does not imply approval. A proposed artifact can be served in a
 * pilot, but the dated approval is the governance record.
 */
enum ModelArtifactStatus: string implements HasColor, HasLabel
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Retired => 'Retired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Approved => 'success',
            self::Proposed => 'warning',
            self::Rejected => 'danger',
            self::Retired => 'gray',
        };
    }
}
