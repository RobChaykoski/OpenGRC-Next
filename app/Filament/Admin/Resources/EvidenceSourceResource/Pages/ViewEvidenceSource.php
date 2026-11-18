<?php

namespace App\Filament\Admin\Resources\EvidenceSourceResource\Pages;

use App\Filament\Admin\Resources\EvidenceSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEvidenceSource extends ViewRecord
{
    protected static string $resource = EvidenceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            EvidenceSourceResource::runAction(),
            DeleteAction::make(),
        ];
    }
}
