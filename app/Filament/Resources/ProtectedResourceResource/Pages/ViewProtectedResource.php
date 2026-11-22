<?php

namespace App\Filament\Resources\ProtectedResourceResource\Pages;

use App\Filament\Resources\ProtectedResourceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProtectedResource extends ViewRecord
{
    protected static string $resource = ProtectedResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
