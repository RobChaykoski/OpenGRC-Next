<?php

namespace App\Filament\Resources\ModelArtifactResource\Pages;

use App\Filament\Resources\ModelArtifactResource;
use Filament\Resources\Pages\ListRecords;

class ListModelArtifacts extends ListRecords
{
    protected static string $resource = ModelArtifactResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
