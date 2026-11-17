<?php

namespace App\Filament\Vendor\Resources\DocumentResource\Pages;

use App\Enums\VendorDocumentStatus;
use App\Filament\Vendor\Resources\DocumentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A corrected upload is a new review cycle, not a rejected document.
        $data['status'] = VendorDocumentStatus::PENDING;
        $data['reviewed_at'] = null;
        $data['review_notes'] = null;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
