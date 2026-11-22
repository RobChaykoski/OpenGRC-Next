<?php

namespace App\Filament\Admin\Resources;

use App\Enums\AssistantProposalState;
use App\Filament\Admin\Resources\AssistantProposalResource\Pages\ListAssistantProposals;
use App\Filament\Admin\Resources\AssistantProposalResource\Pages\ViewAssistantProposal;
use App\Models\AssistantProposal;
use App\Models\User;
use App\Services\Assistant\AssistantProposalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Assistant proposals as typed diffs (ADR 0055).
 *
 * Read-only until a human approves; applying re-checks every before-value. The
 * assistant itself is a read-only catalogue today, so this queue is filled by
 * the proposal service when the write path is enabled, not by a free-form agent.
 */
class AssistantProposalResource extends Resource
{
    protected static ?string $model = AssistantProposal::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 47;

    protected static ?string $navigationLabel = 'Assistant proposals';

    protected static ?string $modelLabel = 'Assistant proposal';

    protected static ?string $pluralModelLabel = 'Assistant proposals';

    protected static ?string $recordTitleAttribute = 'summary';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['approver', 'applier']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Proposed')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('summary')
                    ->wrap()
                    ->limit(60),

                TextColumn::make('state')
                    ->badge()
                    ->sortable(),

                TextColumn::make('operations')
                    ->label('Changes')
                    ->state(fn (AssistantProposal $record): int => $record->operationCount())
                    ->numeric(),

                TextColumn::make('run_id')
                    ->label('Run')
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approver.name')
                    ->label('Approved by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->options(AssistantProposalState::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Approve this typed change set? A separate step applies it, and every before-value is re-checked then.')
                    ->visible(fn (AssistantProposal $record): bool => $record->state === AssistantProposalState::Proposed)
                    ->authorize(fn (AssistantProposal $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (AssistantProposal $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(AssistantProposalService::class)->approve($record, $user);

                        Notification::make()->title('Proposal approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AssistantProposal $record): bool => $record->state === AssistantProposalState::Proposed)
                    ->authorize(fn (AssistantProposal $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (AssistantProposal $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(AssistantProposalService::class)->reject($record, $user, (string) $data['reason']);

                        Notification::make()->title('Proposal rejected')->success()->send();
                    }),

                Action::make('apply')
                    ->label('Apply')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Apply the approved changes? If a record changed since the proposal, the whole set fails and nothing is written.')
                    ->visible(fn (AssistantProposal $record): bool => $record->state === AssistantProposalState::Approved)
                    ->authorize(fn (AssistantProposal $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (AssistantProposal $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        try {
                            app(AssistantProposalService::class)->apply($record, $user);
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Proposal could not be applied')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Proposal applied')->success()->send();
                    }),

                Action::make('rollback')
                    ->label('Roll back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (AssistantProposal $record): bool => $record->state === AssistantProposalState::Applied)
                    ->authorize(fn (AssistantProposal $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (AssistantProposal $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(AssistantProposalService::class)->rollback($record, $user, (string) $data['reason']);

                        Notification::make()->title('Proposal rolled back')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No assistant proposals')
            ->emptyStateDescription('A proposal appears here when the assistant proposes a typed change set. Nothing is written until a human approves and applies it.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposal')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('summary')
                            ->columnSpan(2),

                        TextEntry::make('state')
                            ->badge(),

                        TextEntry::make('rationale')
                            ->columnSpanFull(),

                        TextEntry::make('run_id')
                            ->label('Assistant run')
                            ->fontFamily('mono')
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Proposed')
                            ->dateTime(),

                        TextEntry::make('error')
                            ->label('Last error')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Typed changes')
                    ->schema([
                        TextEntry::make('operations')
                            ->label('')
                            ->formatStateUsing(fn ($state): string => '<pre class="text-xs whitespace-pre-wrap">'
                                .e(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                                .'</pre>')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Decisions')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('approver.name')
                            ->label('Approved by')
                            ->placeholder('—'),

                        TextEntry::make('approved_at')
                            ->label('Approved at')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('applier.name')
                            ->label('Applied by')
                            ->placeholder('—'),

                        TextEntry::make('applied_at')
                            ->label('Applied at')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('rejecter.name')
                            ->label('Rejected by')
                            ->placeholder('—'),

                        TextEntry::make('rejected_reason')
                            ->label('Rejection reason')
                            ->placeholder('—'),

                        TextEntry::make('rollbacker.name')
                            ->label('Rolled back by')
                            ->placeholder('—'),

                        TextEntry::make('rollback_reason')
                            ->label('Rollback reason')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssistantProposals::route('/'),
            'view' => ViewAssistantProposal::route('/{record}'),
        ];
    }
}
