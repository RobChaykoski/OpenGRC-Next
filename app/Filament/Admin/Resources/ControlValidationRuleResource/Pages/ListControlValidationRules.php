<?php

namespace App\Filament\Admin\Resources\ControlValidationRuleResource\Pages;

use App\Filament\Admin\Resources\ControlValidationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListControlValidationRules extends ListRecords
{
    protected static string $resource = ControlValidationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
