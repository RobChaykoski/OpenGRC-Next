<?php

namespace App\Filament\Resources;

use App\Filament\Exports\FileAttachmentExporter;
use App\Filament\Resources\FileAttachmentResource\Pages\CreateFileAttachment;
use App\Filament\Resources\FileAttachmentResource\Pages\EditFileAttachment;
use App\Filament\Resources\FileAttachmentResource\Pages\ListFileAttachments;
use App\Filament\Resources\FileAttachmentResource\Pages\ViewFileAttachment;
use App\Models\EvidenceLedgerEntry;
use App\Models\FileAttachment;
use App\Services\Evidence\EvidenceLedger;
use App\Support\InstanceMode;
use App\Support\UploadRules;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FileAttachmentResource extends Resource
{
    protected static ?string $model = FileAttachment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('description')
                    ->disableToolbarButtons([
                        'image',
                        'attachFiles',
                    ])
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('file_path')
                    ->label('File')
                    ->preserveFilenames()
                    ->acceptedFileTypes(UploadRules::mimes(UploadRules::EVIDENCE))
                    ->maxSize(UploadRules::maxKb(UploadRules::EVIDENCE))
                    ->rules([UploadRules::validationRule(UploadRules::EVIDENCE)])
                    ->disk(setting('storage.driver', 'private'))
                    ->directory(function () {
                        $rand = Carbon::now()->timestamp.'-'.Str::random(2);

                        return 'attachments/'.$rand;
                    })
                    ->downloadable()
                    ->visibility('private')
                    ->openable()
                    ->deletable()
                    ->reorderable()
                    ->columnSpanFull()
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && InstanceMode::isProduction())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                TextColumn::make('description')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->limit()
                    ->wrap(),
                TextColumn::make('file_path')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('file_size')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('evidenceState.status')
                    ->label('Evidence status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'active' => 'success',
                        'withdrawn' => 'danger',
                        'superseded' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('file_sha256')
                    ->label('SHA-256')
                    ->limit(12)
                    ->tooltip(fn (?string $state) => $state)
                    ->toggleable(),
                TextColumn::make('uploaded_by')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(FileAttachmentExporter::class)
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('ledger')
                    ->label('Chain of custody')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalDescription(fn (FileAttachment $record): string => $record->ledgerEntries
                        ->map(fn (EvidenceLedgerEntry $entry) => sprintf(
                            '#%d %s %s %s',
                            $entry->sequence,
                            $entry->event,
                            $entry->created_at?->toDateTimeString(),
                            substr((string) $entry->entry_hash, 0, 12)
                        ))
                        ->implode("\n")),
                Action::make('withdraw')
                    ->label('Withdraw evidence')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn () => auth()->user()?->canWrite() ?? false)
                    ->visible(fn (FileAttachment $record): bool => InstanceMode::isProduction()
                        && (($record->evidenceState()->first()?->getAttribute('status')) ?? 'active') === 'active')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason for withdrawal')
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->action(fn (FileAttachment $record, array $data) => app(EvidenceLedger::class)
                        ->withdraw($record, (string) $data['reason'], auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(FileAttachmentExporter::class)
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFileAttachments::route('/'),
            'create' => CreateFileAttachment::route('/create'),
            'view' => ViewFileAttachment::route('/{record}'),
            'edit' => EditFileAttachment::route('/{record}/edit'),
        ];
    }
}
