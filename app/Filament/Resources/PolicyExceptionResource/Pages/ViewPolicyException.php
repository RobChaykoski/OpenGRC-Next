<?php

namespace App\Filament\Resources\PolicyExceptionResource\Pages;

use App\Filament\Resources\PolicyExceptionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPolicyException extends ViewRecord
{
    protected static string $resource = PolicyExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
