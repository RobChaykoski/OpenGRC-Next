<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use App\Services\Documents\DocumentTemplateManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return app(DocumentTemplateManager::class)->prepare($data);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'data.source_file' => $exception->getMessage(),
            ]);
        }
    }
}
