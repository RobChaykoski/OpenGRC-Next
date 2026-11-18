<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentTemplate extends ViewRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DocumentTemplateResource::approveAction(),
            DocumentTemplateResource::downloadAction(),
        ];
    }
}
