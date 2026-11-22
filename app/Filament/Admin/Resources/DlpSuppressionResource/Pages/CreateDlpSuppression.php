<?php

namespace App\Filament\Admin\Resources\DlpSuppressionResource\Pages;

use App\Filament\Admin\Resources\DlpSuppressionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDlpSuppression extends CreateRecord
{
    protected static string $resource = DlpSuppressionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->id();
        $data['state'] = 'requested';

        return $data;
    }
}
