<?php

namespace App\Filament\Admin\Resources\EvidenceSourceResource\Pages;

use App\Filament\Admin\Resources\EvidenceSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvidenceSource extends CreateRecord
{
    protected static string $resource = EvidenceSourceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['configuration'] = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];

        return $data;
    }
}
