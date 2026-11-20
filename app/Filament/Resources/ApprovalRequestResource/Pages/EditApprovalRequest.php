<?php

namespace App\Filament\Resources\ApprovalRequestResource\Pages;

use App\Filament\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use Filament\Resources\Pages\EditRecord;

class EditApprovalRequest extends EditRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! $this->record instanceof ApprovalRequest) {
            abort(404);
        }

        abort_unless($this->record->isPending(), 403);

        return $data;
    }
}
