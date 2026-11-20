<?php

namespace App\Filament\Resources\RemediationTaskResource\Pages;

use App\Filament\Resources\RemediationTaskResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRemediationTask extends EditRecord
{
    protected static string $resource = RemediationTaskResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['finding_id'] ?? null) && blank($data['risk_id'] ?? null) && blank($data['control_id'] ?? null)) {
            throw ValidationException::withMessages([
                'finding_id' => 'Link at least one finding, risk or control.',
            ]);
        }

        return $data;
    }
}
