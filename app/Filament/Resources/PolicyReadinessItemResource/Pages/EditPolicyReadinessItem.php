<?php

namespace App\Filament\Resources\PolicyReadinessItemResource\Pages;

use App\Filament\Resources\PolicyReadinessItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPolicyReadinessItem extends EditRecord
{
    protected static string $resource = PolicyReadinessItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
