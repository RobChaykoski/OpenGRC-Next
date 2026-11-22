<?php

namespace App\Filament\Resources;

use App\Enums\AccessResourceType;
use App\Enums\ConsentDirective;
use App\Enums\DocumentClassification;
use App\Filament\Resources\ProtectedResourceResource\Pages\CreateProtectedResource;
use App\Filament\Resources\ProtectedResourceResource\Pages\EditProtectedResource;
use App\Filament\Resources\ProtectedResourceResource\Pages\ListProtectedResources;
use App\Filament\Resources\ProtectedResourceResource\Pages\ViewProtectedResource;
use App\Models\ProtectedResource;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The data side of access governance (ADR 0049).
 *
 * A governance record of where sensitive data lives and how it is classified.
 * It holds no sample values and no row content.
 */
class ProtectedResourceResource extends Resource
{
    protected static ?string $model = ProtectedResource::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Access governance';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Protected resources';

    protected static ?string $modelLabel = 'Protected resource';

    protected static ?string $pluralModelLabel = 'Protected resources';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Resource')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('resource_type')
                            ->options(AccessResourceType::class)
                            ->default(AccessResourceType::Dataset->value)
                            ->required(),

                        TextInput::make('external_urn')
                            ->label('External reference')
                            ->maxLength(191)
                            ->unique(ignoreRecord: true)
                            ->helperText('Catalog URN or engine identifier. Not a file path.'),

                        Select::make('asset_id')
                            ->label('Asset')
                            ->relationship('asset', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Optional link to the asset register.'),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('The person accountable for the classification.'),
                    ]),

                Section::make('Classification')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('tier')
                            ->label('Classification level')
                            ->options(DocumentClassification::class)
                            ->default(DocumentClassification::Confidential->value)
                            ->required()
                            ->helperText('The organisation-wide scale. Policy matches on this level.'),

                        TextInput::make('classification_code')
                            ->label('Classification label')
                            ->maxLength(64)
                            ->helperText('Optional industry label, for example phi or cardholder_data.'),

                        TextInput::make('classification_version')
                            ->label('Catalogue version')
                            ->maxLength(32),

                        DateTimePicker::make('classified_at')
                            ->label('Classified at')
                            ->seconds(false)
                            ->helperText('A classification older than the freshness limit is treated as a coverage gap.'),

                        Select::make('classification_source')
                            ->label('Source')
                            ->options([
                                'manual' => 'Manual',
                                'catalog' => 'Data catalog',
                                'discovery' => 'Discovery scan',
                            ])
                            ->default('manual')
                            ->required(),

                        TextInput::make('jurisdiction')
                            ->maxLength(64)
                            ->helperText('Residency or legal jurisdiction, where it differs from the default.'),
                    ]),

                Section::make('Directives')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('consent_directive')
                            ->label('Consent directive')
                            ->options(ConsentDirective::class)
                            ->default(ConsentDirective::None->value)
                            ->required()
                            ->helperText('A lockbox or opt-out raises the justification and review bar. It is not a deny bit.'),

                        Toggle::make('is_crown_jewel')
                            ->label('Crown jewel')
                            ->helperText('High-value data: raised review, not automatic refusal.'),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Ownership, purpose and handling. Do not paste record content.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner')->withCount('grants'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('tier')
                    ->label('Level')
                    ->badge()
                    ->sortable(),

                TextColumn::make('consent_directive')
                    ->label('Directive')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('is_crown_jewel')
                    ->label('Crown jewel')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unowned'),

                TextColumn::make('classified_at')
                    ->label('Classified')
                    ->date()
                    ->sortable()
                    ->color(fn (ProtectedResource $record): string => $record->isStale() ? 'danger' : 'gray')
                    ->description(fn (ProtectedResource $record): ?string => $record->isStale() ? 'Stale' : null),

                TextColumn::make('grants_count')
                    ->label('Grants')
                    ->numeric()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tier')
                    ->label('Level')
                    ->options(DocumentClassification::class),

                SelectFilter::make('consent_directive')
                    ->label('Directive')
                    ->options(ConsentDirective::class),

                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('freshness')
                    ->options([
                        'stale' => 'Stale classification',
                        'current' => 'Current classification',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'stale' => $query->where(function (Builder $query): void {
                            $query->whereNull('classified_at')
                                ->orWhere('classified_at', '<', now()->subDays((int) config('access_governance.classification.stale_after_days', 30)));
                        }),
                        'current' => $query->whereNotNull('classified_at')
                            ->where('classified_at', '>=', now()->subDays((int) config('access_governance.classification.stale_after_days', 30))),
                        default => $query,
                    }),
            ])
            ->emptyStateHeading('No protected resources')
            ->emptyStateDescription('Register a dataset and its classification before writing a policy that depends on it.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resource')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->columnSpan(2),

                        TextEntry::make('resource_type')
                            ->label('Type')
                            ->badge(),

                        TextEntry::make('tier')
                            ->label('Classification level')
                            ->badge(),

                        TextEntry::make('consent_directive')
                            ->label('Directive')
                            ->badge(),

                        TextEntry::make('is_crown_jewel')
                            ->label('Crown jewel')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),

                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unowned'),

                        TextEntry::make('asset.name')
                            ->label('Asset')
                            ->placeholder('Not linked'),

                        TextEntry::make('external_urn')
                            ->label('External reference')
                            ->placeholder('—')
                            ->fontFamily('mono'),
                    ]),

                Section::make('Classification evidence')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('classification_code')
                            ->label('Label')
                            ->placeholder('—'),

                        TextEntry::make('classification_version')
                            ->label('Catalogue version')
                            ->placeholder('—'),

                        TextEntry::make('classification_source')
                            ->label('Source'),

                        TextEntry::make('classified_at')
                            ->label('Classified at')
                            ->dateTime()
                            ->placeholder('Never'),

                        TextEntry::make('classification_age')
                            ->label('Freshness')
                            ->state(fn (ProtectedResource $record): string => match (true) {
                                $record->classified_at === null => 'Never classified',
                                $record->isStale() => 'Stale',
                                default => 'Current',
                            })
                            ->color(fn (ProtectedResource $record): string => $record->isStale() ? 'danger' : 'success'),

                        TextEntry::make('jurisdiction')
                            ->placeholder('—'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProtectedResources::route('/'),
            'create' => CreateProtectedResource::route('/create'),
            'view' => ViewProtectedResource::route('/{record}'),
            'edit' => EditProtectedResource::route('/{record}/edit'),
        ];
    }
}
