<?php

namespace App\Filament\Resources\EvidenceRecordResource\Pages;

use App\Filament\Resources\EvidenceRecordResource;
use App\Models\EvidenceRecord;
use App\Services\Evidence\EvidenceHasher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEvidenceRecord extends ViewRecord
{
    protected static string $resource = EvidenceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyHash')
                ->label(__('evidence.actions.verify_hash'))
                ->icon('heroicon-o-check-badge')
                ->action(function (EvidenceRecord $record): void {
                    $matches = app(EvidenceHasher::class)->matches($record);

                    $notification = Notification::make()->title(
                        $matches
                            ? __('evidence.notifications.hash_ok')
                            : __('evidence.notifications.hash_bad')
                    );

                    if ($matches) {
                        $notification->success();
                    } else {
                        $notification->danger();
                    }

                    $notification->send();
                }),
        ];
    }
}
