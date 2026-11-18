<?php

namespace App\Filament\Admin\Resources;

use App\Enums\EvidenceSourceType;
use App\Filament\Admin\Resources\EvidenceSourceResource\Pages\CreateEvidenceSource;
use App\Filament\Admin\Resources\EvidenceSourceResource\Pages\EditEvidenceSource;
use App\Filament\Admin\Resources\EvidenceSourceResource\Pages\ListEvidenceSources;
use App\Filament\Admin\Resources\EvidenceSourceResource\Pages\ViewEvidenceSource;
use App\Jobs\CollectEvidenceJob;
use App\Models\EvidenceSource;
use App\Services\Evidence\ConnectorRegistry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin-only source configuration. Connector-specific fields are shown per
 * type; the password is never hydrated back into the form.
 */
class EvidenceSourceResource extends Resource
{
    protected static ?string $model = EvidenceSource::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 39;

    public static function getNavigationLabel(): string
    {
        return __('evidence.source.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('evidence.source.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('evidence.source.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('evidence.source.sections.source'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('evidence.source.form.name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Select::make('type')
                            ->label(__('evidence.source.form.type'))
                            ->options(EvidenceSourceType::class)
                            ->required()
                            ->live()
                            ->default(EvidenceSourceType::Patchmon),

                        Toggle::make('enabled')
                            ->label(__('evidence.source.form.enabled'))
                            ->default(true),

                        Select::make('owner_id')
                            ->label(__('evidence.source.form.owner'))
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('schedule')
                            ->label(__('evidence.source.form.schedule'))
                            ->helperText(__('evidence.source.form.schedule_help'))
                            ->maxLength(64),
                    ]),

                Section::make(__('evidence.source.sections.connection'))
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::isPatchmon($get))
                    ->schema([
                        TextInput::make('configuration.base_url')
                            ->label(__('evidence.source.form.base_url'))
                            ->helperText(__('evidence.source.form.base_url_help'))
                            ->required(fn (Get $get): bool => self::isPatchmon($get))
                            ->url(),

                        TextInput::make('configuration.username')
                            ->label(__('evidence.source.form.username'))
                            ->required(fn (Get $get): bool => self::isPatchmon($get))
                            ->maxLength(255),

                        TextInput::make('configuration.password')
                            ->label(__('evidence.source.form.password'))
                            ->helperText(__('evidence.source.form.password_help'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation, Get $get): bool => $operation === 'create' && self::isPatchmon($get))
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->afterStateHydrated(function (TextInput $component): void {
                                // Never send the stored credential to the browser.
                                $component->state(null);
                            }),

                        TextInput::make('configuration.control_identifier')
                            ->label(__('evidence.source.form.control_identifier'))
                            ->helperText(__('evidence.source.form.control_identifier_help'))
                            ->maxLength(255),

                        TextInput::make('configuration.timeout')
                            ->label(__('evidence.source.form.timeout'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->default(15),
                    ]),

                Section::make(__('evidence.source.sections.connection'))
                    ->visible(fn (Get $get): bool => ! self::isPatchmon($get))
                    ->schema([
                        Placeholder::make('no_connector')
                            ->label('')
                            ->content(__('evidence.source.form.no_connector')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('evidence.source.form.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('evidence.source.form.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->searchable(false)
                    ->sortable(),

                IconColumn::make('enabled')
                    ->label(__('evidence.source.form.enabled'))
                    ->boolean(),

                TextColumn::make('last_run_at')
                    ->label(__('evidence.source.table.last_run'))
                    ->since()
                    ->placeholder(__('evidence.source.table.never'))
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label(__('evidence.source.form.owner'))
                    ->placeholder('Unassigned')
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label(__('evidence.rule.table.updated_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('evidence.source.form.type'))
                    ->options(EvidenceSourceType::class),

                SelectFilter::make('enabled')
                    ->label(__('evidence.source.form.enabled'))
                    ->options(['1' => 'Enabled', '0' => 'Disabled']),

                SelectFilter::make('owner_id')
                    ->label(__('evidence.source.form.owner'))
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::runAction(),
                DeleteAction::make(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading(__('evidence.source.table.empty_heading'))
            ->emptyStateDescription(__('evidence.source.table.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('evidence.source.sections.source'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('evidence.source.form.name')),

                        TextEntry::make('type')
                            ->label(__('evidence.source.form.type'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel()),

                        TextEntry::make('enabled')
                            ->label(__('evidence.source.form.enabled'))
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ? 'Enabled' : 'Disabled')
                            ->color(fn ($state): string => $state ? 'success' : 'gray'),

                        TextEntry::make('owner.name')
                            ->label(__('evidence.source.form.owner'))
                            ->placeholder('Unassigned'),

                        TextEntry::make('last_run_at')
                            ->label(__('evidence.source.table.last_run'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('evidence.source.table.never')),

                        TextEntry::make('schedule')
                            ->label(__('evidence.source.form.schedule'))
                            ->placeholder('—'),
                    ]),

                Section::make(__('evidence.source.sections.connection'))
                    ->columns(3)
                    ->visible(fn (EvidenceSource $record): bool => $record->type === EvidenceSourceType::Patchmon)
                    ->schema([
                        TextEntry::make('configuration.base_url')
                            ->label(__('evidence.source.form.base_url'))
                            ->placeholder('—'),

                        TextEntry::make('configuration.username')
                            ->label(__('evidence.source.form.username'))
                            ->placeholder('—'),

                        TextEntry::make('configuration.control_identifier')
                            ->label(__('evidence.source.form.control_identifier'))
                            ->placeholder('—'),

                        TextEntry::make('credential')
                            ->label(__('evidence.source.infolist.credential'))
                            ->state(fn (EvidenceSource $record): string => filled($record->configuration['password'] ?? null)
                                ? __('evidence.source.infolist.configured')
                                : __('evidence.source.infolist.not_set'))
                            ->badge()
                            ->color(fn (string $state): string => $state === __('evidence.source.infolist.configured') ? 'success' : 'gray'),
                    ]),
            ]);
    }

    public static function runAction(): Action
    {
        return Action::make('runCollection')
            ->label(__('evidence.source.actions.run'))
            ->icon('heroicon-o-play')
            ->requiresConfirmation()
            ->modalDescription(__('evidence.source.actions.run_description'))
            ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
            ->visible(fn (EvidenceSource $record): bool => $record->isEnabled()
                && app(ConnectorRegistry::class)->has($record->type))
            ->action(function (EvidenceSource $record): void {
                CollectEvidenceJob::dispatch($record->getKey());

                Notification::make()
                    ->title(__('evidence.source.notifications.queued'))
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvidenceSources::route('/'),
            'create' => CreateEvidenceSource::route('/create'),
            'view' => ViewEvidenceSource::route('/{record}'),
            'edit' => EditEvidenceSource::route('/{record}/edit'),
        ];
    }

    protected static function isPatchmon(Get $get): bool
    {
        $type = $get('type');

        return $type instanceof EvidenceSourceType
            ? $type === EvidenceSourceType::Patchmon
            : $type === EvidenceSourceType::Patchmon->value;
    }
}
