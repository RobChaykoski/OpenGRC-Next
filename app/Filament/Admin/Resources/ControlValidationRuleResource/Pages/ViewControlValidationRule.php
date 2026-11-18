<?php

namespace App\Filament\Admin\Resources\ControlValidationRuleResource\Pages;

use App\Filament\Admin\Resources\ControlValidationRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewControlValidationRule extends ViewRecord
{
    protected static string $resource = ControlValidationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
