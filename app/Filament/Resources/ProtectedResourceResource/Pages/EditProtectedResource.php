<?php

namespace App\Filament\Resources\ProtectedResourceResource\Pages;

use App\Filament\Resources\ProtectedResourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProtectedResource extends EditRecord
{
    protected static string $resource = ProtectedResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
