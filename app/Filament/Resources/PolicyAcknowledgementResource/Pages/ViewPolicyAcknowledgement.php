<?php

namespace App\Filament\Resources\PolicyAcknowledgementResource\Pages;

use App\Filament\Resources\PolicyAcknowledgementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPolicyAcknowledgement extends ViewRecord
{
    protected static string $resource = PolicyAcknowledgementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
