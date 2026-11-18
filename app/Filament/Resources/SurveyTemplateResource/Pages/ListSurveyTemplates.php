<?php

namespace App\Filament\Resources\SurveyTemplateResource\Pages;

use App\Filament\Resources\SurveyTemplateResource;
use App\Models\SurveyTemplate;
use App\Services\Survey\QuestionnaireWorkbookImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListSurveyTemplates extends ListRecords
{
    protected static string $resource = SurveyTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(),
            CreateAction::make(),
        ];
    }

    private function importAction(): Action
    {
        return Action::make('importQuestionnaire')
            ->label(__('survey.template.actions.import_questionnaire'))
            ->icon('heroicon-o-arrow-up-tray')
            ->modalDescription(__('survey.template.actions.import_description'))
            ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
            ->form([
                TextInput::make('title')
                    ->label(__('survey.template.actions.import_name'))
                    ->required()
                    ->maxLength(255)
                    ->default('Risk assessment questionnaire'),

                FileUpload::make('file')
                    ->label(__('survey.template.actions.import_file'))
                    ->helperText(__('survey.template.actions.import_file_help'))
                    ->disk('local')
                    ->directory('questionnaires')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->required(),

                Toggle::make('replace')
                    ->label(__('survey.template.actions.import_replace'))
                    ->helperText(__('survey.template.actions.import_replace_helper')),

                Toggle::make('dry_run')
                    ->label(__('survey.template.actions.import_dry_run')),
            ])
            ->action(function (array $data): void {
                $file = $data['file'];
                $relative = is_array($file) ? (string) array_values($file)[0] : (string) $file;
                $absolute = Storage::disk('local')->path($relative);

                try {
                    $importer = app(QuestionnaireWorkbookImporter::class);
                    $parsed = $importer->read($absolute);
                    $count = collect($parsed['sections'])->sum(fn (array $section): int => count($section['questions']));

                    if ((bool) ($data['dry_run'] ?? false)) {
                        Notification::make()
                            ->title(__('survey.template.notifications.previewed', [
                                'count' => $count,
                                'sections' => count($parsed['sections']),
                            ]))
                            ->success()
                            ->send();

                        return;
                    }

                    $template = SurveyTemplate::query()->where('title', $data['title'])->first()
                        ?? $importer->createTemplate((string) $data['title']);

                    $importer->importParsed($parsed, $template, (bool) ($data['replace'] ?? false));

                    Notification::make()
                        ->title(__('survey.template.notifications.imported', [
                            'count' => $count,
                            'name' => $template->title,
                        ]))
                        ->success()
                        ->send();

                    $this->redirect(SurveyTemplateResource::getUrl('index'));
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title(__('survey.template.notifications.import_failed', [
                            'error' => $exception->getMessage(),
                        ]))
                        ->danger()
                        ->send();
                }
            });
    }
}
