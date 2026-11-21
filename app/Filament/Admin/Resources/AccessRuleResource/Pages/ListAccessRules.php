<?php

namespace App\Filament\Admin\Resources\AccessRuleResource\Pages;

use App\Filament\Admin\Resources\AccessRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccessRules extends ListRecords
{
    protected static string $resource = AccessRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('access-rules.actions.create')),
        ];
    }
}
