<?php

namespace App\Filament\Resources;

use App\Enums\PolicyDocumentationStatus;
use App\Enums\PolicyEffectivenessStatus;
use App\Enums\PolicyImplementationStatus;
use App\Filament\Resources\PolicyReadinessItemResource\Pages\EditPolicyReadinessItem;
use App\Filament\Resources\PolicyReadinessItemResource\Pages\ListPolicyReadinessItems;
use App\Filament\Resources\PolicyReadinessItemResource\Pages\ViewPolicyReadinessItem;
use App\Models\PolicyReadinessItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

class PolicyReadinessItemResource extends Resource
{
    protected static ?string $model = PolicyReadinessItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 45;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('policy_readiness.navigation.label');
    }

    public static function getNavigationGroup(): string
    {
        return __('policy_readiness.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('policy_readiness.model.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('policy_readiness.model.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['assessment', 'policy']))
            ->columns([
                TextColumn::make('code')
                    ->label(__('policy_readiness.table.code'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('policy_readiness.table.title'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(70),

                TextColumn::make('category')
                    ->label(__('policy_readiness.table.category'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('priority')
                    ->label(__('policy_readiness.table.priority'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => (string) $state)
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'critical', 'required / critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    })
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('documentation_status')
                    ->label(__('policy_readiness.table.documentation'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('implementation_status')
                    ->label(__('policy_readiness.table.implementation'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('effectiveness_status')
                    ->label(__('policy_readiness.table.effectiveness'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('owner')
                    ->label(__('policy_readiness.table.owner'))
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('applicability')
                    ->label(__('policy_readiness.table.applicability'))
                    ->badge()
                    ->color(fn (?string $state): string => str_contains(strtolower((string) $state), 'not applicable') ? 'gray' : 'success')
                    ->searchable(false)
                    ->toggleable(),

                TextColumn::make('target_date')
                    ->label(__('policy_readiness.table.target_date'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_reviewed')
                    ->label(__('policy_readiness.table.last_reviewed'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('assessment.name')
                    ->label(__('policy_readiness.table.assessment'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('policy_readiness.filters.category'))
                    ->options(fn (): array => PolicyReadinessItem::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),

                SelectFilter::make('priority')
                    ->label(__('policy_readiness.filters.priority'))
                    ->options(fn (): array => PolicyReadinessItem::query()
                        ->whereNotNull('priority')
                        ->distinct()
                        ->orderBy('priority')
                        ->pluck('priority', 'priority')
                        ->all()),

                SelectFilter::make('applicability')
                    ->label(__('policy_readiness.filters.applicability'))
                    ->options(fn (): array => PolicyReadinessItem::query()
                        ->whereNotNull('applicability')
                        ->distinct()
                        ->orderBy('applicability')
                        ->pluck('applicability', 'applicability')
                        ->all()),

                SelectFilter::make('documentation_status')
                    ->label(__('policy_readiness.filters.documentation'))
                    ->options(PolicyDocumentationStatus::class),

                SelectFilter::make('implementation_status')
                    ->label(__('policy_readiness.filters.implementation'))
                    ->options(PolicyImplementationStatus::class),

                SelectFilter::make('effectiveness_status')
                    ->label(__('policy_readiness.filters.effectiveness'))
                    ->options(PolicyEffectivenessStatus::class),

                SelectFilter::make('owner')
                    ->label(__('policy_readiness.filters.owner'))
                    ->options(fn (): array => PolicyReadinessItem::query()
                        ->whereNotNull('owner')
                        ->distinct()
                        ->orderBy('owner')
                        ->pluck('owner', 'owner')
                        ->all()),

                Filter::make('needs_attention')
                    ->label(__('policy_readiness.filters.needs_attention'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereIn(
                        'id',
                        PolicyReadinessItem::query()->needsAttention()->select('id')
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('code')
            ->emptyStateHeading(__('policy_readiness.table.empty_heading'))
            ->emptyStateDescription(__('policy_readiness.table.empty_description'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('policy_readiness.sections.item'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('policy_readiness.form.code'))
                            ->required()
                            ->maxLength(32),

                        TextInput::make('title')
                            ->label(__('policy_readiness.form.title'))
                            ->required()
                            ->maxLength(512),

                        TextInput::make('category')
                            ->label(__('policy_readiness.form.category'))
                            ->maxLength(64),

                        TextInput::make('priority')
                            ->label(__('policy_readiness.form.priority'))
                            ->maxLength(32),

                        TextInput::make('applicability')
                            ->label(__('policy_readiness.form.applicability'))
                            ->maxLength(32),

                        TextInput::make('owner')
                            ->label(__('policy_readiness.form.owner'))
                            ->maxLength(255),

                        TextInput::make('target_date')
                            ->label(__('policy_readiness.form.target_date'))
                            ->helperText(__('policy_readiness.form.target_date_help'))
                            ->maxLength(64),

                        TextInput::make('last_reviewed')
                            ->label(__('policy_readiness.form.last_reviewed'))
                            ->maxLength(64),

                        Select::make('policy_id')
                            ->label(__('policy_readiness.form.policy'))
                            ->relationship('policy', 'name')
                            ->searchable(),
                    ]),

                Section::make(__('policy_readiness.sections.status'))
                    ->columns(3)
                    ->schema([
                        Select::make('documentation_status')
                            ->label(__('policy_readiness.table.documentation'))
                            ->options(PolicyDocumentationStatus::class)
                            ->required(),

                        Select::make('implementation_status')
                            ->label(__('policy_readiness.table.implementation'))
                            ->options(PolicyImplementationStatus::class)
                            ->required(),

                        Select::make('effectiveness_status')
                            ->label(__('policy_readiness.table.effectiveness'))
                            ->options(PolicyEffectivenessStatus::class)
                            ->required(),
                    ]),

                Section::make(__('policy_readiness.sections.mapping'))
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Textarea::make('framework_applicability')
                            ->label(__('policy_readiness.form.framework_applicability'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('nist_csf')
                            ->label(__('policy_readiness.form.nist_csf'))
                            ->rows(2),

                        Textarea::make('itsg33')
                            ->label(__('policy_readiness.form.itsg33'))
                            ->rows(2),

                        Textarea::make('cmmc')
                            ->label(__('policy_readiness.form.cmmc'))
                            ->rows(2),

                        Textarea::make('objective')
                            ->label(__('policy_readiness.form.objective'))
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('expected_evidence')
                            ->label(__('policy_readiness.form.expected_evidence'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('policy_readiness.sections.evidence'))
                    ->schema([
                        TextInput::make('evidence_location')
                            ->label(__('policy_readiness.form.evidence_location'))
                            ->maxLength(512)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label(__('policy_readiness.form.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('policy_readiness.sections.item'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label(__('policy_readiness.table.code'))
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('title')
                            ->label(__('policy_readiness.table.title'))
                            ->weight('semibold')
                            ->columnSpan(2),

                        TextEntry::make('category')
                            ->label(__('policy_readiness.table.category'))
                            ->placeholder('—'),

                        TextEntry::make('priority')
                            ->label(__('policy_readiness.table.priority'))
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('applicability')
                            ->label(__('policy_readiness.table.applicability'))
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('owner')
                            ->label(__('policy_readiness.table.owner'))
                            ->placeholder('—'),

                        TextEntry::make('target_date')
                            ->label(__('policy_readiness.table.target_date'))
                            ->placeholder('—'),

                        TextEntry::make('last_reviewed')
                            ->label(__('policy_readiness.table.last_reviewed'))
                            ->placeholder('—'),

                        TextEntry::make('policy.name')
                            ->label(__('policy_readiness.form.policy'))
                            ->placeholder('—')
                            ->url(fn (PolicyReadinessItem $record): ?string => $record->policy
                                ? PolicyResource::getUrl('view', ['record' => $record->policy])
                                : null),

                        TextEntry::make('assessment.name')
                            ->label(__('policy_readiness.table.assessment'))
                            ->placeholder('—'),
                    ]),

                Section::make(__('policy_readiness.sections.status'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('documentation_status')
                            ->label(__('policy_readiness.table.documentation'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('implementation_status')
                            ->label(__('policy_readiness.table.implementation'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('effectiveness_status')
                            ->label(__('policy_readiness.table.effectiveness'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),
                    ]),

                Section::make(__('policy_readiness.sections.mapping'))
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('framework_applicability')
                            ->label(__('policy_readiness.form.framework_applicability'))
                            ->columnSpanFull(),

                        TextEntry::make('nist_csf')
                            ->label(__('policy_readiness.form.nist_csf')),

                        TextEntry::make('itsg33')
                            ->label(__('policy_readiness.form.itsg33')),

                        TextEntry::make('cmmc')
                            ->label(__('policy_readiness.form.cmmc')),

                        TextEntry::make('objective')
                            ->label(__('policy_readiness.form.objective'))
                            ->columnSpanFull(),

                        TextEntry::make('expected_evidence')
                            ->label(__('policy_readiness.form.expected_evidence'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('policy_readiness.sections.evidence'))
                    ->schema([
                        TextEntry::make('evidence_location')
                            ->label(__('policy_readiness.form.evidence_location'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label(__('policy_readiness.form.notes'))
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap']),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolicyReadinessItems::route('/'),
            'view' => ViewPolicyReadinessItem::route('/{record}'),
            'edit' => EditPolicyReadinessItem::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'title', 'owner'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->code.' — '.$record->title;
    }
}
