<?php

namespace App\Filament\Resources;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Filament\Resources\FindingResource\Pages\CreateFinding;
use App\Filament\Resources\FindingResource\Pages\EditFinding;
use App\Filament\Resources\FindingResource\Pages\ListFindings;
use App\Filament\Resources\FindingResource\Pages\ViewFinding;
use App\Models\Finding;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FindingResource extends Resource
{
    protected static ?string $model = Finding::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 35;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('finding.navigation.label');
    }

    public static function getNavigationGroup(): string
    {
        return __('finding.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('finding.model.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('finding.model.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('finding.sections.finding'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('finding.form.title'))
                            ->helperText(__('finding.form.title_help'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('severity')
                            ->label(__('finding.form.severity'))
                            ->options(EvidenceSeverity::class)
                            ->required()
                            ->default(EvidenceSeverity::Medium),

                        Select::make('status')
                            ->label(__('finding.form.status'))
                            ->options(FindingStatus::class)
                            ->required()
                            ->default(FindingStatus::Open),

                        Textarea::make('description')
                            ->label(__('finding.form.description'))
                            ->helperText(__('finding.form.description_help'))
                            ->required()
                            ->rows(6)
                            ->columnSpanFull(),

                        Select::make('control_id')
                            ->label(__('finding.form.control'))
                            ->relationship('control', 'code')
                            ->searchable(),

                        Select::make('asset_id')
                            ->label(__('finding.form.asset'))
                            ->relationship('asset', 'name')
                            ->searchable(),

                        Select::make('owner_id')
                            ->label(__('finding.form.owner'))
                            ->helperText(__('finding.form.owner_help'))
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('due_at')
                            ->label(__('finding.form.due_at')),

                        Textarea::make('resolution_notes')
                            ->label(__('finding.form.resolution_notes'))
                            ->helperText(__('finding.form.resolution_notes_help'))
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(function (Get $get): bool {
                                $status = $get('status');

                                if (! $status instanceof FindingStatus) {
                                    $status = FindingStatus::tryFrom((string) $status);
                                }

                                return in_array($status, [FindingStatus::Resolved, FindingStatus::Accepted], true);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['control', 'asset', 'owner']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('finding.entries.reference'))
                    ->formatStateUsing(fn (Finding $record): string => $record->reference())
                    ->badge()
                    ->color('gray')
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('finding.table.title'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('severity')
                    ->label(__('finding.table.severity'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('finding.table.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('control.code')
                    ->label(__('finding.table.control'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('asset.name')
                    ->label(__('finding.table.asset'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('owner.name')
                    ->label(__('finding.table.owner'))
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('due_at')
                    ->label(__('finding.table.due_at'))
                    ->dateTime('M j, Y')
                    ->placeholder('No due date')
                    ->color(fn (Finding $record): string => $record->isOpen() && $record->due_at?->isPast() ? 'danger' : 'gray')
                    ->icon(fn (Finding $record): ?string => $record->isOpen() && $record->due_at?->isPast() ? 'heroicon-m-exclamation-triangle' : null)
                    ->sortable(),

                TextColumn::make('source')
                    ->label(__('finding.table.source'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->searchable(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('occurrences')
                    ->label(__('finding.table.occurrences'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('identified_at')
                    ->label(__('finding.table.identified_at'))
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_seen_at')
                    ->label(__('finding.table.last_seen_at'))
                    ->since()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('finding.filters.status'))
                    ->options(FindingStatus::class)
                    ->multiple(),

                SelectFilter::make('severity')
                    ->label(__('finding.filters.severity'))
                    ->options(EvidenceSeverity::class)
                    ->multiple(),

                SelectFilter::make('source')
                    ->label(__('finding.filters.source'))
                    ->options(FindingSource::class),

                SelectFilter::make('owner_id')
                    ->label(__('finding.filters.owner'))
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('overdue')
                    ->label(__('finding.filters.overdue'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [FindingStatus::Open, FindingStatus::InProgress])
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::resolveAction(),
                self::acceptRiskAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignOwner')
                        ->label(__('finding.actions.assign_owner'))
                        ->icon('heroicon-o-user')
                        ->authorize(fn (): bool => auth()->user()?->can('create', Finding::class) ?? false)
                        ->form([
                            Select::make('owner_id')
                                ->label(__('finding.form.owner'))
                                ->relationship('owner', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            $records->each->update(['owner_id' => $data['owner_id']]);
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('identified_at', 'desc')
            ->emptyStateHeading(__('finding.table.empty_heading'))
            ->emptyStateDescription(__('finding.table.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('finding.sections.finding'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')
                            ->label(__('finding.entries.reference'))
                            ->state(fn (Finding $record): string => $record->reference())
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('title')
                            ->label(__('finding.form.title'))
                            ->weight('semibold')
                            ->columnSpan(2),

                        TextEntry::make('severity')
                            ->label(__('finding.form.severity'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('status')
                            ->label(__('finding.form.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('source')
                            ->label(__('finding.table.source'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel()),

                        TextEntry::make('description')
                            ->label(__('finding.form.description'))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap']),
                    ]),

                Section::make(__('finding.sections.mapping'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('control.code')
                            ->label(__('finding.form.control'))
                            ->placeholder('—')
                            ->url(fn (Finding $record): ?string => $record->control
                                ? ControlResource::getUrl('view', ['record' => $record->control])
                                : null),

                        TextEntry::make('asset.name')
                            ->label(__('finding.form.asset'))
                            ->placeholder('—'),

                        TextEntry::make('owner.name')
                            ->label(__('finding.form.owner'))
                            ->placeholder('Unassigned'),

                        TextEntry::make('evidenceSource.name')
                            ->label(__('evidence.table.source'))
                            ->placeholder('Manual'),
                    ]),

                Section::make(__('finding.sections.timeline'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('identified_at')
                            ->label(__('finding.table.identified_at'))
                            ->dateTime('M j, Y H:i'),

                        TextEntry::make('last_seen_at')
                            ->label(__('finding.table.last_seen_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('due_at')
                            ->label(__('finding.form.due_at'))
                            ->dateTime('M j, Y')
                            ->placeholder('—')
                            ->color(fn (Finding $record): string => $record->isOpen() && $record->due_at?->isPast() ? 'danger' : 'gray'),

                        TextEntry::make('resolved_at')
                            ->label('Resolved')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('occurrences')
                            ->label(__('finding.entries.occurrences')),
                    ]),

                Section::make(__('finding.sections.resolution'))
                    ->schema([
                        TextEntry::make('resolution_notes')
                            ->label(__('finding.form.resolution_notes'))
                            ->placeholder('No resolution recorded.')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap']),
                    ])
                    ->visible(fn (Finding $record): bool => filled($record->resolution_notes)),
            ]);
    }

    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label(__('finding.actions.resolve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('finding.actions.resolve_confirm'))
            ->form([
                Textarea::make('resolution_notes')
                    ->label(__('finding.form.resolution_notes'))
                    ->rows(3),
            ])
            ->authorize(fn (Finding $record): bool => auth()->user()?->can('resolve', $record) ?? false)
            ->visible(fn (Finding $record): bool => $record->isOpen())
            ->action(function (Finding $record, array $data): void {
                $record->resolve($data['resolution_notes'] ?? null);

                Notification::make()
                    ->title(__('finding.notifications.resolved'))
                    ->success()
                    ->send();
            });
    }

    public static function acceptRiskAction(): Action
    {
        return Action::make('acceptRisk')
            ->label(__('finding.actions.accept_risk'))
            ->icon('heroicon-o-shield-exclamation')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('finding.actions.accept_confirm'))
            ->form([
                Textarea::make('resolution_notes')
                    ->label(__('finding.form.resolution_notes'))
                    ->rows(3),
            ])
            ->authorize(fn (Finding $record): bool => auth()->user()?->can('accept', $record) ?? false)
            ->visible(fn (Finding $record): bool => $record->isOpen())
            ->action(function (Finding $record, array $data): void {
                $record->acceptRisk($data['resolution_notes'] ?? null);

                Notification::make()
                    ->title(__('finding.notifications.accepted'))
                    ->warning()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFindings::route('/'),
            'create' => CreateFinding::route('/create'),
            'view' => ViewFinding::route('/{record}'),
            'edit' => EditFinding::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->reference().' — '.$record->title;
    }
}
