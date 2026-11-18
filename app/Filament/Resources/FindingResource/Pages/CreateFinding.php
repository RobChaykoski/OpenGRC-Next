<?php

namespace App\Filament\Resources\FindingResource\Pages;

use App\Enums\FindingSource;
use App\Filament\Resources\FindingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinding extends CreateRecord
{
    protected static string $resource = FindingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = FindingSource::Manual;
        $data['identified_at'] = now();
        $data['occurrences'] = 1;

        return $data;
    }
}
