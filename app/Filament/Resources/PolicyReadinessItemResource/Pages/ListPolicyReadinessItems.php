<?php

namespace App\Filament\Resources\PolicyReadinessItemResource\Pages;

use App\Filament\Resources\PolicyReadinessItemResource;
use App\Filament\Widgets\PolicyReadinessStatsWidget;
use App\Services\PolicyReadiness\PolicyReadinessWorkbookImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListPolicyReadinessItems extends ListRecords
{
    protected static string $resource = PolicyReadinessItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importChecklist')
                ->label(__('policy_readiness.actions.import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->modalDescription(__('policy_readiness.actions.import_description'))
                ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
                ->form([
                    TextInput::make('name')
                        ->label(__('policy_readiness.actions.name'))
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => 'Policy readiness '.now()->format('Y-m-d')),

                    FileUpload::make('file')
                        ->label(__('policy_readiness.actions.file'))
                        ->helperText(__('policy_readiness.actions.file_help'))
                        ->disk('local')
                        ->directory('policy-readiness')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'];
                    $path = is_array($file) ? (string) array_values($file)[0] : (string) $file;
                    $absolute = Storage::disk('local')->path($path);

                    try {
                        $assessment = app(PolicyReadinessWorkbookImporter::class)->importFile(
                            $absolute,
                            (string) $data['name'],
                            basename($path),
                        );

                        Notification::make()
                            ->title(__('policy_readiness.notifications.imported', [
                                'count' => $assessment->items()->count(),
                                'name' => $assessment->name,
                            ]))
                            ->success()
                            ->send();

                        $this->redirect(PolicyReadinessItemResource::getUrl('index'));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title(__('policy_readiness.notifications.failed', [
                                'error' => $exception->getMessage(),
                            ]))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PolicyReadinessStatsWidget::class,
        ];
    }
}
