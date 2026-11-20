<?php

namespace App\Filament\Resources\ImplementationResource\RelationManagers;

use App\Models\FileAttachment;
use App\Support\UploadRules;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Human evidence for a control implementation.
 *
 * The file is private, hashed by the evidence ledger and append-only in
 * production. This relation makes the evidence visible on the implementation
 * instead of forcing it into an audit item (gap 3 in docs/grc/README.md).
 */
class EvidenceRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Evidence';

    protected static ?string $recordTitleAttribute = 'file_name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('description')
                    ->label('Description')
                    ->helperText('What this file shows and which period or test it supports.')
                    ->required()
                    ->maxLength(1024)
                    ->columnSpanFull(),

                FileUpload::make('file_path')
                    ->label('File')
                    ->required()
                    ->preserveFilenames()
                    ->acceptedFileTypes(UploadRules::mimes(UploadRules::EVIDENCE))
                    ->maxSize(UploadRules::maxKb(UploadRules::EVIDENCE))
                    ->rules([UploadRules::validationRule(UploadRules::EVIDENCE)])
                    ->disk(setting('storage.driver', 'private'))
                    ->directory(fn (): string => 'implementation-evidence/'.now()->timestamp.'-'.Str::random(6))
                    ->storeFileNamesIn('file_name')
                    ->visibility('private')
                    ->downloadable()
                    ->openable()
                    ->deletable()
                    ->columnSpanFull(),

                Hidden::make('uploaded_by')
                    ->default(fn (): ?int => Auth::id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('file_name')
                    ->label('File')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (FileAttachment $record): ?string => $record->description),

                TextColumn::make('file_sha256')
                    ->label('SHA-256')
                    ->limit(12)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder('System')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add evidence'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (FileAttachment $record): bool => $record->file_path !== null
                        && Storage::disk(setting('storage.driver', 'private'))->exists($record->file_path))
                    ->url(fn (FileAttachment $record): string => URL::temporarySignedRoute(
                        'attachments.download',
                        now()->addMinutes(5),
                        ['fileAttachment' => $record]
                    )),
            ])
            ->emptyStateHeading('No evidence attached')
            ->emptyStateDescription('Attach the screenshot, report or export that shows this control is operating.');
    }
}
