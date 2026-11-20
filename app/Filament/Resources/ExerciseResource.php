<?php

namespace App\Filament\Resources;

use App\Enums\ExerciseSource;
use App\Enums\ExerciseStatus;
use App\Enums\ExerciseType;
use App\Filament\Resources\ExerciseResource\Pages\CreateExercise;
use App\Filament\Resources\ExerciseResource\Pages\EditExercise;
use App\Filament\Resources\ExerciseResource\Pages\ListExercises;
use App\Filament\Resources\ExerciseResource\Pages\ViewExercise;
use App\Models\Exercise;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
 * Exercise register: tabletops, simulations and red-team exercises.
 *
 * A CTEP or vendor scenario stays upstream. This record keeps the objectives,
 * participants, findings, corrective actions and evidence reference.
 */
class ExerciseResource extends Resource
{
    protected static ?string $model = Exercise::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationLabel = 'Exercises';

    protected static ?string $modelLabel = 'Exercise';

    protected static ?string $pluralModelLabel = 'Exercises';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Exercise')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label('Type')
                            ->options(ExerciseType::class)
                            ->required(),

                        Select::make('source')
                            ->label('Source')
                            ->options(ExerciseSource::class)
                            ->default(ExerciseSource::Internal)
                            ->required(),

                        TextInput::make('source_reference')
                            ->label('Source reference')
                            ->helperText('A CISA CTEP name or package id. Link, do not copy the package.')
                            ->maxLength(128),

                        Select::make('status')
                            ->label('Status')
                            ->options(ExerciseStatus::class)
                            ->default(ExerciseStatus::Planned)
                            ->required(),

                        Select::make('facilitator_id')
                            ->label('Facilitator')
                            ->relationship('facilitator', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Scenario and scope')
                    ->schema([
                        Textarea::make('scenario')
                            ->label('Scenario')
                            ->helperText('A short summary, not the full package.')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('objectives')
                            ->label('Objectives')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('scope')
                            ->label('Scope and participants')
                            ->helperText('Teams and roles, not personal profiles.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Schedule and outcome')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('scheduled_at')
                            ->label('Scheduled'),

                        DatePicker::make('conducted_at')
                            ->label('Conducted'),

                        TagsInput::make('participants')
                            ->label('Participant groups')
                            ->placeholder('Add a team or role')
                            ->columnSpanFull(),

                        Textarea::make('findings_summary')
                            ->label('Findings summary')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('corrective_actions')
                            ->label('Corrective actions')
                            ->helperText('The actions that follow; link a finding or risk where possible.')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('evidence_reference')
                            ->label('Evidence reference')
                            ->maxLength(255),

                        TextInput::make('evidence_sha256')
                            ->label('Evidence SHA-256')
                            ->maxLength(64),

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('facilitator'))
            ->defaultSort('scheduled_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (Exercise $record): string => $record->reference()),

                TextColumn::make('name')
                    ->label('Exercise')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('source')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('conducted_at')
                    ->label('Conducted')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('facilitator.name')
                    ->label('Facilitator')
                    ->placeholder('Unassigned')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ExerciseType::class),

                SelectFilter::make('source')
                    ->options(ExerciseSource::class),

                SelectFilter::make('status')
                    ->options(ExerciseStatus::class),

                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No exercises')
            ->emptyStateDescription('Register a tabletop, simulation or red-team exercise and record what it found.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Exercise')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->columnSpanFull(),

                        TextEntry::make('reference')
                            ->label('Reference')
                            ->state(fn (Exercise $record): string => $record->reference()),

                        TextEntry::make('type')
                            ->badge(),

                        TextEntry::make('source')
                            ->badge(),

                        TextEntry::make('source_reference')
                            ->label('Source reference')
                            ->placeholder('—'),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('facilitator.name')
                            ->label('Facilitator')
                            ->placeholder('Unassigned'),

                        TextEntry::make('scheduled_at')
                            ->label('Scheduled')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('conducted_at')
                            ->label('Conducted')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('participants')
                            ->label('Participant groups')
                            ->state(fn (Exercise $record): string => implode(', ', $record->participants ?? []) ?: '—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Scenario and outcome')
                    ->schema([
                        TextEntry::make('scenario')
                            ->label('Scenario')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('objectives')
                            ->label('Objectives')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('scope')
                            ->label('Scope')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('findings_summary')
                            ->label('Findings summary')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('corrective_actions')
                            ->label('Corrective actions')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('evidence_reference')
                            ->label('Evidence reference')
                            ->placeholder('—'),

                        TextEntry::make('evidence_sha256')
                            ->label('Evidence SHA-256')
                            ->placeholder('—'),

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
            'index' => ListExercises::route('/'),
            'create' => CreateExercise::route('/create'),
            'view' => ViewExercise::route('/{record}'),
            'edit' => EditExercise::route('/{record}/edit'),
        ];
    }
}
