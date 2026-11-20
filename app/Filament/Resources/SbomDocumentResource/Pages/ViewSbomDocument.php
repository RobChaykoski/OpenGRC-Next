<?php

namespace App\Filament\Resources\SbomDocumentResource\Pages;

use App\Filament\Resources\SbomDocumentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSbomDocument extends ViewRecord
{
    protected static string $resource = SbomDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
