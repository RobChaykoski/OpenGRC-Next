<?php

namespace App\Filament\Resources\AccessPolicyResource\Pages;

use App\Filament\Resources\AccessPolicyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAccessPolicy extends ViewRecord
{
    protected static string $resource = AccessPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
