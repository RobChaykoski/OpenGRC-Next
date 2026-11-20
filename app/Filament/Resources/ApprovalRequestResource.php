<?php

namespace App\Filament\Resources;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Filament\Resources\ApprovalRequestResource\Pages\CreateApprovalRequest;
use App\Filament\Resources\ApprovalRequestResource\Pages\EditApprovalRequest;
use App\Filament\Resources\ApprovalRequestResource\Pages\ListApprovalRequests;
use App\Filament\Resources\ApprovalRequestResource\Pages\ViewApprovalRequest;
use App\Filament\Resources\ApprovalRequestResource\RelationManagers\StepsRelationManager;
use App\Models\ApprovalRequest;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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
 * A register of multi-step approval requests.
 *
 * The steps live on the request. This is not a per-field workflow engine and it
 * is not attached to a particular policy, risk or vendor yet.
 */
class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 47;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return 'Approval requests';
    }

    public static function getModelLabel(): string
    {
        return 'Approval request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Approval requests';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Request')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->helperText('What is being approved? Keep it specific.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),

                        DatePicker::make('due_at')
                            ->label('Decision due'),

                        Hidden::make('requested_by')
                            ->default(fn (): ?int => auth()->id()),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title')
                            ->columnSpanFull(),

                        TextEntry::make('reference')
                            ->label('Reference')
                            ->state(fn (ApprovalRequest $record): string => $record->reference()),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?ApprovalRequestStatus $state): ?string => $state?->getLabel())
                            ->color(fn (?ApprovalRequestStatus $state): string|array|null => $state?->getColor()),

                        TextEntry::make('requestedBy.name')
                            ->label('Requested by')
                            ->placeholder('—'),

                        TextEntry::make('due_at')
                            ->label('Decision due')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('completed_at')
                            ->label('Completed')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('current_approver')
                            ->label('Waiting on')
                            ->state(fn (ApprovalRequest $record): string => $record->currentStep()?->approver?->name ?? '—'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['requestedBy', 'steps.approver']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (ApprovalRequest $record): string => $record->reference())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('title')
                    ->label('Request')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?ApprovalRequestStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?ApprovalRequestStatus $state): string|array|null => $state?->getColor()),

                TextColumn::make('current_approver')
                    ->label('Waiting on')
                    ->state(fn (ApprovalRequest $record): string => $record->currentStep()?->approver?->name ?? '—'),

                TextColumn::make('steps_count')
                    ->label('Steps')
                    ->state(fn (ApprovalRequest $record): string => $record->steps->where('status', ApprovalStepStatus::Approved)->count().' / '.$record->steps->count()),

                TextColumn::make('due_at')
                    ->label('Due')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (ApprovalRequest $record): string => $record->isOverdue() ? 'danger' : 'gray'),

                TextColumn::make('requestedBy.name')
                    ->label('Requested by')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ApprovalRequestStatus::class),

                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', ApprovalRequestStatus::Pending->value)
                        ->whereNotNull('due_at')
                        ->whereDate('due_at', '<', today())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (ApprovalRequest $record): bool => $record->isPending()),
            ])
            ->emptyStateHeading('No approval requests')
            ->emptyStateDescription('Create a request and add the people who must approve it in order.');
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRequests::route('/'),
            'create' => CreateApprovalRequest::route('/create'),
            'view' => ViewApprovalRequest::route('/{record}'),
            'edit' => EditApprovalRequest::route('/{record}/edit'),
        ];
    }
}
