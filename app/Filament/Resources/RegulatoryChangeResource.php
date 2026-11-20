<?php

namespace App\Filament\Resources;

use App\Enums\RegulatoryChangeImpact;
use App\Enums\RegulatoryChangeStatus;
use App\Filament\Resources\RegulatoryChangeResource\Pages\CreateRegulatoryChange;
use App\Filament\Resources\RegulatoryChangeResource\Pages\EditRegulatoryChange;
use App\Filament\Resources\RegulatoryChangeResource\Pages\ListRegulatoryChanges;
use App\Filament\Resources\RegulatoryChangeResource\Pages\ViewRegulatoryChange;
use App\Models\RegulatoryChange;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A register of new or amended regulatory and standard requirements.
 *
 * The source stays upstream. The register records the link, the affected
 * frameworks and the organisation's impact assessment (ADR 0039).
 */
class RegulatoryChangeResource extends Resource
{
    protected static ?string $model = RegulatoryChange::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 42;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('regulatory_change.navigation.label');
    }

    public static function getModelLabel(): string
    {
        return __('regulatory_change.model.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('regulatory_change.model.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('regulatory_change.sections.change'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('regulatory_change.form.title'))
                            ->helperText(__('regulatory_change.form.title_help'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('regulator')
                            ->label(__('regulatory_change.form.regulator'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('reference_url')
                            ->label(__('regulatory_change.form.reference_url'))
                            ->helperText(__('regulatory_change.form.reference_url_help'))
                            ->url()
                            ->maxLength(512),

                        DatePicker::make('published_at')
                            ->label(__('regulatory_change.form.published_at')),

                        DatePicker::make('effective_at')
                            ->label(__('regulatory_change.form.effective_at')),

                        Select::make('status')
                            ->label(__('regulatory_change.form.status'))
                            ->options(RegulatoryChangeStatus::class)
                            ->default(RegulatoryChangeStatus::New)
                            ->required(),

                        Select::make('impact')
                            ->label(__('regulatory_change.form.impact'))
                            ->options(RegulatoryChangeImpact::class),
                    ]),

                Section::make(__('regulatory_change.sections.assessment'))
                    ->columns(2)
                    ->schema([
                        Textarea::make('summary')
                            ->label(__('regulatory_change.form.summary'))
                            ->helperText(__('regulatory_change.form.summary_help'))
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('impact_assessment')
                            ->label(__('regulatory_change.form.impact_assessment'))
                            ->helperText(__('regulatory_change.form.impact_assessment_help'))
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('owner_id')
                            ->label(__('regulatory_change.form.owner'))
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('due_at')
                            ->label(__('regulatory_change.form.due_at')),

                        DatePicker::make('assessed_at')
                            ->label(__('regulatory_change.form.assessed_at')),

                        Textarea::make('notes')
                            ->label(__('regulatory_change.form.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('regulatory_change.sections.mapping'))
                    ->schema([
                        Select::make('standards')
                            ->label(__('regulatory_change.form.standards'))
                            ->helperText(__('regulatory_change.form.standards_help'))
                            ->relationship('standards', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner')->withCount('standards'))
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('regulatory_change.table.reference'))
                    ->getStateUsing(fn (RegulatoryChange $record): string => $record->reference())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('title')
                    ->label(__('regulatory_change.table.title'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('regulator')
                    ->label(__('regulatory_change.table.regulator'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('regulatory_change.table.status'))
                    ->badge()
                    ->formatStateUsing(fn (?RegulatoryChangeStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?RegulatoryChangeStatus $state): string|array|null => $state?->getColor())
                    ->sortable(),

                TextColumn::make('impact')
                    ->label(__('regulatory_change.table.impact'))
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?RegulatoryChangeImpact $state): ?string => $state?->getLabel())
                    ->color(fn (?RegulatoryChangeImpact $state): string|array|null => $state?->getColor())
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label(__('regulatory_change.table.published_at'))
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('effective_at')
                    ->label(__('regulatory_change.table.effective_at'))
                    ->date()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('due_at')
                    ->label(__('regulatory_change.table.due_at'))
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (RegulatoryChange $record): string => $record->isOverdue() ? 'danger' : 'gray'),

                TextColumn::make('owner.name')
                    ->label(__('regulatory_change.table.owner'))
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('standards_count')
                    ->label(__('regulatory_change.table.standards'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RegulatoryChangeStatus::class),

                SelectFilter::make('impact')
                    ->options(RegulatoryChangeImpact::class),

                SelectFilter::make('owner_id')
                    ->label(__('regulatory_change.filters.owner'))
                    ->relationship('owner', 'name')
                    ->searchable(),

                Filter::make('overdue')
                    ->label(__('regulatory_change.filters.overdue'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', RegulatoryChangeStatus::openCases())
                        ->whereNotNull('due_at')
                        ->whereDate('due_at', '<', today())),
            ])
            ->emptyStateHeading(__('regulatory_change.table.empty_heading'))
            ->emptyStateDescription(__('regulatory_change.table.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('regulatory_change.sections.change'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('regulatory_change.form.title'))
                            ->columnSpanFull(),

                        TextEntry::make('reference')
                            ->label(__('regulatory_change.table.reference'))
                            ->state(fn (RegulatoryChange $record): string => $record->reference()),

                        TextEntry::make('regulator')
                            ->label(__('regulatory_change.form.regulator')),

                        TextEntry::make('status')
                            ->label(__('regulatory_change.form.status'))
                            ->badge()
                            ->formatStateUsing(fn (?RegulatoryChangeStatus $state): ?string => $state?->getLabel())
                            ->color(fn (?RegulatoryChangeStatus $state): string|array|null => $state?->getColor()),

                        TextEntry::make('impact')
                            ->label(__('regulatory_change.form.impact'))
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?RegulatoryChangeImpact $state): ?string => $state?->getLabel())
                            ->color(fn (?RegulatoryChangeImpact $state): string|array|null => $state?->getColor()),

                        TextEntry::make('reference_url')
                            ->label(__('regulatory_change.form.reference_url'))
                            ->placeholder('—')
                            ->url(fn (RegulatoryChange $record): ?string => $record->reference_url)
                            ->openUrlInNewTab(),

                        TextEntry::make('published_at')
                            ->label(__('regulatory_change.form.published_at'))
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('effective_at')
                            ->label(__('regulatory_change.form.effective_at'))
                            ->date()
                            ->placeholder('—'),
                    ]),

                Section::make(__('regulatory_change.sections.assessment'))
                    ->schema([
                        TextEntry::make('summary')
                            ->label(__('regulatory_change.form.summary'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('impact_assessment')
                            ->label(__('regulatory_change.form.impact_assessment'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('owner.name')
                            ->label(__('regulatory_change.form.owner'))
                            ->placeholder('Unassigned'),

                        TextEntry::make('due_at')
                            ->label(__('regulatory_change.form.due_at'))
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('assessed_at')
                            ->label(__('regulatory_change.form.assessed_at'))
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label(__('regulatory_change.form.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('standards.name')
                            ->label(__('regulatory_change.form.standards'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegulatoryChanges::route('/'),
            'create' => CreateRegulatoryChange::route('/create'),
            'view' => ViewRegulatoryChange::route('/{record}'),
            'edit' => EditRegulatoryChange::route('/{record}/edit'),
        ];
    }
}
