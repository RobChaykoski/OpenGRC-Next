<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccessResourceType: string implements HasLabel
{
    case Dataset = 'dataset';
    case Table = 'table';
    case ColumnSet = 'column_set';
    case RecordScope = 'record_scope';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dataset => 'Dataset',
            self::Table => 'Table',
            self::ColumnSet => 'Column set',
            self::RecordScope => 'Record scope',
            self::Api => 'API',
        };
    }
}
