<?php

namespace App\Filament\Resources\AccessReviewResource\RelationManagers;

use App\Enums\AccessReviewDecision as DecisionType;
use App\Models\AccessReview;
use App\Models\AccessReviewDecision as Decision;
use App\Services\Access\AccessReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use LogicException;
use Throwable;

class DecisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'decisions';

    protected static ?string $title = 'Accounts';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'decidedBy']))
            ->recordTitleAttribute('user_id')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Account')
                    ->formatStateUsing(fn (?string $state, Decision $record): string => $record->user?->displayName() ?? ($state ?? '—'))
                    ->searchable(),

                TextColumn::make('role_at_review')
                    ->label('Role')
                    ->badge(),

                IconColumn::make('mfa_at_review')
                    ->label('MFA')
                    ->boolean(),

                TextColumn::make('decision')
                    ->label('Decision')
                    ->badge(),

                TextColumn::make('decidedBy.name')
                    ->label('Decided by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('decided_at')
                    ->label('Decided')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('user.name')
            ->recordActions([
                $this->decisionAction('attest', 'Attest', DecisionType::Attest, 'success', 'heroicon-o-check-circle'),
                $this->decisionAction('revoke', 'Revoke', DecisionType::Revoke, 'danger', 'heroicon-o-x-circle'),
                $this->decisionAction('notApplicable', 'Not applicable', DecisionType::NotApplicable, 'gray', 'heroicon-o-minus-circle'),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function review(): AccessReview
    {
        $record = $this->getOwnerRecord();

        if (! $record instanceof AccessReview) {
            throw new LogicException('The relation owner is not an access review.');
        }

        return $record;
    }

    protected function decisionAction(
        string $name,
        string $label,
        DecisionType $type,
        string $color,
        string $icon,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (): bool => $this->review()->isActive())
            ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
            ->form([
                Textarea::make('notes')
                    ->label('Decision notes')
                    ->rows(2),
            ])
            ->action(function (Decision $record, array $data) use ($type): void {
                try {
                    app(AccessReviewService::class)->decide(
                        $record,
                        $type,
                        auth()->user(),
                        is_string($data['notes'] ?? null) ? $data['notes'] : null,
                    );
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Decision not recorded')
                        ->body($exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unexpected error.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Decision recorded')
                    ->body($record->user?->displayName().': '.$type->getLabel())
                    ->success()
                    ->send();
            });
    }
}
