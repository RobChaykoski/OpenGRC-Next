<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentTemplateManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        try {
            return app(DocumentTemplateManager::class)->prepare(
                $data,
                $record instanceof DocumentTemplate ? $record : null,
            );
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'data.source_file' => $exception->getMessage(),
            ]);
        }
    }
}
