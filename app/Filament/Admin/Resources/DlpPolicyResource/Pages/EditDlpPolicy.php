<?php

namespace App\Filament\Admin\Resources\DlpPolicyResource\Pages;

use App\Filament\Admin\Resources\DlpPolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDlpPolicy extends EditRecord
{
    protected static string $resource = DlpPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
