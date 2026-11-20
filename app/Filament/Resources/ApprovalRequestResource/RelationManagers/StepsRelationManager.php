<?php

namespace App\Filament\Resources\ApprovalRequestResource\RelationManagers;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\User;
use App\Services\Controls\ApprovalWorkflow;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

/**
 * The ordered approval chain for one request.
 *
 * Only the current pending step can be decided. A rejection skips the rest of
 * the chain rather than leaving it open.
 */
class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Approval steps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('approver_id')
                    ->label('Approver')
                    ->relationship('approver', 'name')
                    ->searchable()
                    ->required(),

                Hidden::make('status')
                    ->default(ApprovalStepStatus::Pending->value),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->columns([
                TextColumn::make('sequence')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('approver.name')
                    ->label('Approver')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?ApprovalStepStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?ApprovalStepStatus $state): string|array|null => $state?->getColor()),

                TextColumn::make('decidedBy.name')
                    ->label('Decided by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('decided_at')
                    ->label('Decided')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—'),

                TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add step')
                    ->visible(fn (): bool => $this->stepCanBeAdded()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ApprovalRequestStep $record): bool => $this->canDecide($record))
                    ->schema([
                        Textarea::make('comment')
                            ->label('Comment')
                            ->rows(2),
                    ])
                    ->action(function (ApprovalRequestStep $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User || ! $record->request instanceof ApprovalRequest) {
                            return;
                        }

                        try {
                            app(ApprovalWorkflow::class)->approve($record->request, $record, $user, $data['comment'] ?? null);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        $this->resetTable();
                        Notification::make()->title('Step approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ApprovalRequestStep $record): bool => $this->canDecide($record))
                    ->schema([
                        Textarea::make('comment')
                            ->label('Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (ApprovalRequestStep $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User || ! $record->request instanceof ApprovalRequest) {
                            return;
                        }

                        try {
                            app(ApprovalWorkflow::class)->reject($record->request, $record, $user, (string) $data['comment']);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        $this->resetTable();
                        Notification::make()->title('Step rejected')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('No approval steps')
            ->emptyStateDescription('Add the people who must approve, in order.');
    }

    protected function stepCanBeAdded(): bool
    {
        $request = $this->request();

        return $request instanceof ApprovalRequest
            && $request->status === ApprovalRequestStatus::Pending
            && ! $request->steps()->where('status', '!=', ApprovalStepStatus::Pending->value)->exists();
    }

    protected function canDecide(ApprovalRequestStep $step): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $step->status->isPending() || ! $step->isCurrent()) {
            return false;
        }

        return $user->isAdmin() || (int) $step->approver_id === (int) $user->getKey();
    }

    protected function request(): ?ApprovalRequest
    {
        return ApprovalRequest::query()->find($this->getOwnerRecord()->getKey());
    }
}
