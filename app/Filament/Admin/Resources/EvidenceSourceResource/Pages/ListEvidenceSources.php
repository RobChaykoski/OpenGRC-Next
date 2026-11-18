<?php

namespace App\Filament\Admin\Resources\EvidenceSourceResource\Pages;

use App\Filament\Admin\Resources\EvidenceSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvidenceSources extends ListRecords
{
    protected static string $resource = EvidenceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
