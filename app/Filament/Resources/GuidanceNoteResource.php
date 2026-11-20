<?php

namespace App\Filament\Resources;

use App\Enums\GuidanceStatus;
use App\Filament\Resources\GuidanceNoteResource\Pages\CreateGuidanceNote;
use App\Filament\Resources\GuidanceNoteResource\Pages\EditGuidanceNote;
use App\Filament\Resources\GuidanceNoteResource\Pages\ListGuidanceNotes;
use App\Filament\Resources\GuidanceNoteResource\Pages\ViewGuidanceNote;
use App\Models\GuidanceNote;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Guidance and advisory register.
 *
 * One row per publication, with the adoption decision, owner, review date and the
 * controls it informs. The text stays upstream; the register records what the
 * organisation decided.
 */
class GuidanceNoteResource extends Resource
{
    protected static ?string $model = GuidanceNote::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Guidance & advisories';

    protected static ?string $modelLabel = 'Guidance note';

    protected static ?string $pluralModelLabel = 'Guidance & advisories';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Publication')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('publisher')
                            ->label('Publisher')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('reference')
                            ->label('Reference')
                            ->helperText('For example ITSAP.100.49, ITSM.60.043 or a CISA advisory id.')
                            ->maxLength(64),

                        TextInput::make('version')
                            ->label('Version')
                            ->maxLength(32),

                        DatePicker::make('published_at')
                            ->label('Published'),

                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->maxLength(512)
                            ->columnSpanFull(),

                        TextInput::make('licence')
                            ->label('Content licence')
                            ->helperText('For example Open Government Licence – Canada. Link, do not copy.')
                            ->maxLength(128)
                            ->columnSpanFull(),
                    ]),

                Section::make('Adoption and review')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(GuidanceStatus::class)
                            ->default(GuidanceStatus::New)
                            ->required(),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('reviewed_at')
                            ->label('Last reviewed'),

                        DatePicker::make('next_review_at')
                            ->label('Next review'),

                        Textarea::make('applicability')
                            ->label('Applicability')
                            ->helperText('Which teams, systems or obligations does this affect?')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('summary')
                            ->label('Summary')
                            ->helperText('A short decision note, not a copy of the guidance.')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('controls')
                            ->label('Controls informed')
                            ->relationship('controls', 'title')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "({$record->code}) {$record->title}")
                            ->searchable(['code', 'title'])
                            ->multiple()
                            ->preload()
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Internal notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner')->withCount('controls'))
            ->defaultSort('next_review_at')
            ->columns([
                TextColumn::make('title')
                    ->label('Guidance')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('publisher')
                    ->label('Publisher')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('controls_count')
                    ->label('Controls')
                    ->numeric(),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),

                TextColumn::make('next_review_at')
                    ->label('Next review')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (GuidanceNote $record): string => $record->isReviewDue() ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(GuidanceStatus::class),

                SelectFilter::make('publisher')
                    ->label('Publisher')
                    ->options(fn (): array => GuidanceNote::query()
                        ->distinct()
                        ->orderBy('publisher')
                        ->pluck('publisher', 'publisher')
                        ->all()),

                SelectFilter::make('review')
                    ->label('Review')
                    ->options([
                        'due' => 'Review overdue',
                        'no_owner' => 'No owner',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'due' => GuidanceNote::applyReviewDue($query),
                            'no_owner' => $query->whereNull('owner_id'),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No guidance registered')
            ->emptyStateDescription('Add a Cyber Centre, CISA, DoD or internal advisory when it affects your controls.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Publication')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title')
                            ->columnSpanFull(),

                        TextEntry::make('publisher')
                            ->label('Publisher'),

                        TextEntry::make('reference')
                            ->label('Reference')
                            ->placeholder('—'),

                        TextEntry::make('version')
                            ->label('Version')
                            ->placeholder('—'),

                        TextEntry::make('published_at')
                            ->label('Published')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('licence')
                            ->label('Content licence')
                            ->placeholder('—'),

                        TextEntry::make('url')
                            ->label('URL')
                            ->url(fn (GuidanceNote $record): ?string => $record->url)
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Adoption')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unassigned'),

                        TextEntry::make('next_review_at')
                            ->label('Next review')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('applicability')
                            ->label('Applicability')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('summary')
                            ->label('Summary')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('controls')
                            ->label('Controls informed')
                            ->state(fn (GuidanceNote $record): string => $record->controls
                                ->map(fn ($control): string => $control->code)
                                ->implode(', ') ?: '—')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Internal notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuidanceNotes::route('/'),
            'create' => CreateGuidanceNote::route('/create'),
            'view' => ViewGuidanceNote::route('/{record}'),
            'edit' => EditGuidanceNote::route('/{record}/edit'),
        ];
    }
}
