<?php

namespace App\Filament\Resources\AccessReviewResource\Pages;

use App\Filament\Resources\AccessReviewResource;
use App\Services\Access\AccessReviewService;
use Filament\Resources\Pages\CreateRecord;

class CreateAccessReview extends CreateRecord
{
    protected static string $resource = AccessReviewResource::class;

    protected function afterCreate(): void
    {
        app(AccessReviewService::class)->start($this->record, auth()->user());
    }
}
