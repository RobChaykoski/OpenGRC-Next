<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExerciseType: string implements HasColor, HasLabel
{
    case Tabletop = 'tabletop';
    case Simulation = 'simulation';
    case Functional = 'functional';
    case RedTeam = 'red_team';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tabletop => 'Tabletop',
            self::Simulation => 'Simulation',
            self::Functional => 'Functional',
            self::RedTeam => 'Red team',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Tabletop => 'info',
            self::Simulation, self::Functional => 'warning',
            self::RedTeam => 'danger',
            self::Other => 'gray',
        };
    }
}
