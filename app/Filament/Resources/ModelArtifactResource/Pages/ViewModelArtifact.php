<?php

namespace App\Filament\Resources\ModelArtifactResource\Pages;

use App\Filament\Resources\ModelArtifactResource;
use Filament\Resources\Pages\ViewRecord;

class ViewModelArtifact extends ViewRecord
{
    protected static string $resource = ModelArtifactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ModelArtifactResource::approveAction(),
            ModelArtifactResource::withdrawApprovalAction(),
            ModelArtifactResource::retireAction(),
        ];
    }
}
