<?php

namespace App\Filament\Resources\AccessReviewResource\Pages;

use App\Filament\Resources\AccessReviewResource;
use App\Models\AccessReview;
use App\Services\Access\AccessReviewService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;
use LogicException;
use Throwable;

class ViewAccessReview extends ViewRecord
{
    protected static string $resource = AccessReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label('Complete review')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->review()->isActive() && $this->review()->pendingCount() === 0)
                ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
                ->action(function (): void {
                    try {
                        app(AccessReviewService::class)->complete($this->review());
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Could not complete the review')
                            ->body($exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unexpected error.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Access review completed')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function review(): AccessReview
    {
        $record = $this->record;

        if (! $record instanceof AccessReview) {
            throw new LogicException('The page record is not an access review.');
        }

        return $record;
    }
}
