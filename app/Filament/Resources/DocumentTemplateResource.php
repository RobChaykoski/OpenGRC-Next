<?php

namespace App\Filament\Resources;

use App\Enums\DocumentClassification;
use App\Enums\DocumentTemplateStatus;
use App\Enums\DocumentTemplateType;
use App\Filament\Resources\DocumentTemplateResource\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplateResource\Pages\EditDocumentTemplate;
use App\Filament\Resources\DocumentTemplateResource\Pages\ListDocumentTemplates;
use App\Filament\Resources\DocumentTemplateResource\Pages\ViewDocumentTemplate;
use App\Models\DocumentTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?int $navigationSort = 46;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Document templates';
    }

    public static function getNavigationGroup(): string
    {
        return 'Entities';
    }

    public static function getModelLabel(): string
    {
        return 'Document template';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Document templates';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->canWrite()) {
            $query->whereIn('classification', [
                DocumentClassification::Public->value,
                DocumentClassification::Internal->value,
            ]);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner']))
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('version')
                    ->label('Version')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('classification')
                    ->label('Classification')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('placeholders')
                    ->label('Placeholders')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): int => is_array($state) ? count($state) : 0)
                    ->searchable(false),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime('M j, Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(DocumentTemplateType::class),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(DocumentTemplateStatus::class),

                SelectFilter::make('classification')
                    ->label('Classification')
                    ->options(DocumentClassification::class),

                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::approveAction(),
                DeleteAction::make(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No document templates')
            ->emptyStateDescription('Upload an NDA, scope, assessment, or report template to start the library.');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label('Code')
                            ->maxLength(32)
                            ->unique(ignoreRecord: true),

                        Select::make('type')
                            ->label('Type')
                            ->options(DocumentTemplateType::class)
                            ->required(),

                        TextInput::make('version')
                            ->label('Version')
                            ->default('1.0')
                            ->required()
                            ->maxLength(32),

                        Select::make('status')
                            ->label('Status')
                            ->options(DocumentTemplateStatus::class)
                            ->default(DocumentTemplateStatus::Draft)
                            ->required(),

                        Select::make('classification')
                            ->label('Classification')
                            ->options(DocumentClassification::class)
                            ->default(DocumentClassification::Internal)
                            ->required(),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('policy_id')
                            ->label('Linked policy')
                            ->relationship('policy', 'name')
                            ->searchable(),

                        DatePicker::make('retention_until')
                            ->label('Retention until')
                            ->helperText('Leave blank until a retention decision is recorded.'),
                    ]),

                Section::make('Source')
                    ->schema([
                        FileUpload::make('source_file')
                            ->label('Template file')
                            ->disk('private')
                            ->directory('document-templates')
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'text/html',
                            ])
                            ->maxSize(20480)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('DOCX or HTML. The original stays on the private disk; the extracted text is encrypted at rest.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->weight('semibold')
                            ->columnSpan(2),

                        TextEntry::make('code')
                            ->label('Code')
                            ->placeholder('—'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('version')
                            ->label('Version'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('classification')
                            ->label('Classification')
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unassigned'),

                        TextEntry::make('policy.name')
                            ->label('Linked policy')
                            ->placeholder('—'),

                        TextEntry::make('approved_at')
                            ->label('Approved')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('source_sha256')
                            ->label('Source SHA-256')
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Placeholders')
                    ->schema([
                        TextEntry::make('placeholders')
                            ->label('')
                            ->state(fn (DocumentTemplate $record): array => $record->placeholderList())
                            ->listWithLineBreaks()
                            ->placeholder('None detected'),
                    ]),

                Section::make('Structure')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('outline')
                            ->label('')
                            ->state(fn (DocumentTemplate $record): string => collect($record->outline ?? [])
                                ->map(fn (array $heading): string => str_repeat('— ', max(0, (int) ($heading['level'] ?? 1) - 1)).($heading['text'] ?? ''))
                                ->implode("\n"))
                            ->extraAttributes(['class' => 'whitespace-pre-wrap text-sm'])
                            ->placeholder('No headings detected'),
                    ]),

                Section::make('Extracted text')
                    ->schema([
                        TextEntry::make('body_text')
                            ->label('')
                            ->extraAttributes(['class' => 'whitespace-pre-wrap text-sm'])
                            ->placeholder('No text extracted'),
                    ]),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->authorize(fn (DocumentTemplate $record): bool => auth()->user()?->can('approve', $record) ?? false)
            ->visible(fn (DocumentTemplate $record): bool => ! $record->isApproved() && $record->status !== DocumentTemplateStatus::Retired)
            ->action(function (DocumentTemplate $record): void {
                $record->update([
                    'status' => DocumentTemplateStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Template approved.')
                    ->success()
                    ->send();
            });
    }

    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Download original')
            ->icon('heroicon-o-arrow-down-tray')
            ->url(fn (DocumentTemplate $record): string => route('document-templates.download', $record))
            ->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTemplates::route('/'),
            'create' => CreateDocumentTemplate::route('/create'),
            'view' => ViewDocumentTemplate::route('/{record}'),
            'edit' => EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
