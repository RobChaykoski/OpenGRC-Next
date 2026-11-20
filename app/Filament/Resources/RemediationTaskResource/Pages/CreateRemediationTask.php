<?php

namespace App\Filament\Resources\RemediationTaskResource\Pages;

use App\Filament\Resources\RemediationTaskResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateRemediationTask extends CreateRecord
{
    protected static string $resource = RemediationTaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $this->assertLinked($data);

        return parent::handleRecordCreation($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertLinked(array $data): void
    {
        if (blank($data['finding_id'] ?? null) && blank($data['risk_id'] ?? null) && blank($data['control_id'] ?? null)) {
            throw ValidationException::withMessages([
                'finding_id' => 'Link at least one finding, risk or control.',
            ]);
        }
    }
}
