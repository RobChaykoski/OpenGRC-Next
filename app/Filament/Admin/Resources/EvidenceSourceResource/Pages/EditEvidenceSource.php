<?php

namespace App\Filament\Admin\Resources\EvidenceSourceResource\Pages;

use App\Filament\Admin\Resources\EvidenceSourceResource;
use App\Models\EvidenceSource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvidenceSource extends EditRecord
{
    protected static string $resource = EvidenceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EvidenceSourceResource::runAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $existing = $record instanceof EvidenceSource && is_array($record->configuration)
            ? $record->configuration
            : [];
        $submitted = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];

        $data['configuration'] = EvidenceSource::mergeConfiguration($existing, $submitted);

        return $data;
    }
}
