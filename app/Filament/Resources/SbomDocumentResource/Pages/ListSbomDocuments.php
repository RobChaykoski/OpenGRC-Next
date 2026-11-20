<?php

namespace App\Filament\Resources\SbomDocumentResource\Pages;

use App\Filament\Resources\SbomDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListSbomDocuments extends ListRecords
{
    protected static string $resource = SbomDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
