<?php

namespace App\Filament\Resources\PolicyReadinessItemResource\Pages;

use App\Filament\Resources\PolicyReadinessItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPolicyReadinessItem extends ViewRecord
{
    protected static string $resource = PolicyReadinessItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
