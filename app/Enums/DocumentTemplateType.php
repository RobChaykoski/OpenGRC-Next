<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DocumentTemplateType: string implements HasColor, HasIcon, HasLabel
{
    case Nda = 'nda';
    case Policy = 'policy';
    case Scope = 'scope';
    case Procedure = 'procedure';
    case Assessment = 'assessment';
    case Report = 'report';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nda => 'Non-disclosure agreement',
            self::Policy => 'Policy',
            self::Scope => 'Scope and definitions',
            self::Procedure => 'Procedure',
            self::Assessment => 'Assessment',
            self::Report => 'Report',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Nda => 'warning',
            self::Policy => 'primary',
            self::Scope => 'info',
            self::Procedure => 'success',
            self::Assessment => 'info',
            self::Report => 'warning',
            self::Other => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Nda => 'heroicon-o-lock-closed',
            self::Policy => 'heroicon-o-document-text',
            self::Scope => 'heroicon-o-map',
            self::Procedure => 'heroicon-o-list-bullet',
            self::Assessment => 'heroicon-o-clipboard-document-check',
            self::Report => 'heroicon-o-document-chart-bar',
            self::Other => 'heroicon-o-document',
        };
    }
}
