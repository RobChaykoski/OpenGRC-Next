<?php

namespace App\Filament\Resources;

use App\Enums\RemediationTaskStatus;
use App\Filament\Resources\RemediationTaskResource\Pages\CreateRemediationTask;
use App\Filament\Resources\RemediationTaskResource\Pages\EditRemediationTask;
use App\Filament\Resources\RemediationTaskResource\Pages\ListRemediationTasks;
use App\Filament\Resources\RemediationTaskResource\Pages\ViewRemediationTask;
use App\Models\RemediationTask;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
 * The work that closes a finding, risk or control gap.
 *
 * A task has an owner and a date. It is not a project plan: no dependencies,
 * milestones or ticket sync.
 */
class RemediationTaskResource extends Resource
{
    protected static ?string $model = RemediationTask::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 45;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return 'Remediation tasks';
    }

    public static function getModelLabel(): string
    {
        return 'Remediation task';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Remediation tasks';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Task')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('due_at')
                            ->label('Due'),

                        Select::make('status')
                            ->label('Status')
                            ->options(RemediationTaskStatus::class)
                            ->default(RemediationTaskStatus::Open)
                            ->required(),
                    ]),

                Section::make('Linked record')
                    ->columns(3)
                    ->schema([
                        Select::make('finding_id')
                            ->label('Finding')
                            ->relationship('finding', 'title')
                            ->searchable(),

                        Select::make('risk_id')
                            ->label('Risk')
                            ->relationship('risk', 'name')
                            ->searchable(),

                        Select::make('control_id')
                            ->label('Control')
                            ->relationship('control', 'code')
                            ->searchable(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Task')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title')
                            ->columnSpanFull(),

                        TextEntry::make('reference')
                            ->label('Reference')
                            ->state(fn (RemediationTask $record): string => $record->reference()),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?RemediationTaskStatus $state): ?string => $state?->getLabel())
                            ->color(fn (?RemediationTaskStatus $state): string|array|null => $state?->getColor()),

                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('—'),

                        TextEntry::make('due_at')
                            ->label('Due')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('completed_at')
                            ->label('Completed')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('finding.title')
                            ->label('Finding')
                            ->placeholder('—'),

                        TextEntry::make('risk.name')
                            ->label('Risk')
                            ->placeholder('—'),

                        TextEntry::make('control.code')
                            ->label('Control')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['owner', 'finding', 'risk', 'control']))
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (RemediationTask $record): string => $record->reference())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('title')
                    ->label('Task')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?RemediationTaskStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?RemediationTaskStatus $state): string|array|null => $state?->getColor()),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->searchable(),

                TextColumn::make('due_at')
                    ->label('Due')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (RemediationTask $record): string => $record->isOverdue() ? 'danger' : 'gray'),

                TextColumn::make('finding.title')
                    ->label('Finding')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('risk.name')
                    ->label('Risk')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('control.code')
                    ->label('Control')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RemediationTaskStatus::class),

                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable(),

                SelectFilter::make('finding_id')
                    ->label('Finding')
                    ->relationship('finding', 'title')
                    ->searchable(),

                SelectFilter::make('risk_id')
                    ->label('Risk')
                    ->relationship('risk', 'name')
                    ->searchable(),

                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [
                            RemediationTaskStatus::Open->value,
                            RemediationTaskStatus::InProgress->value,
                            RemediationTaskStatus::Blocked->value,
                        ])
                        ->whereNotNull('due_at')
                        ->whereDate('due_at', '<', today())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('No remediation tasks')
            ->emptyStateDescription('Create a task for the work that closes a finding, risk or control gap.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRemediationTasks::route('/'),
            'create' => CreateRemediationTask::route('/create'),
            'view' => ViewRemediationTask::route('/{record}'),
            'edit' => EditRemediationTask::route('/{record}/edit'),
        ];
    }
}
