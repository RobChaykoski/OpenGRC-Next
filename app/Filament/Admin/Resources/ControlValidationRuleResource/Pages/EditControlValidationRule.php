<?php

namespace App\Filament\Admin\Resources\ControlValidationRuleResource\Pages;

use App\Filament\Admin\Resources\ControlValidationRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditControlValidationRule extends EditRecord
{
    protected static string $resource = ControlValidationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
