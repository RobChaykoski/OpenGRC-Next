<?php

namespace App\Filament\Resources\AccessGrantResource\Pages;

use App\Enums\AccessGrantState;
use App\Enums\PrivacyReviewOutcome;
use App\Filament\Resources\AccessGrantResource;
use App\Models\AccessGrant;
use App\Models\User;
use App\Services\Access\AccessGrantService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;
use LogicException;
use Throwable;

class ViewAccessGrant extends ViewRecord
{
    protected static string $resource = AccessGrantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve and activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve this grant?')
                ->modalDescription(fn (): string => 'Activates access to '
                    .($this->grant()->resource?->name ?? 'the resource')
                    .' until '.($this->grant()->expires_at?->toDayDateTimeString() ?? 'the expiry time').'.')
                ->visible(fn (): bool => $this->grant()->state === AccessGrantState::Requested)
                ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
                ->action(function (): void {
                    try {
                        app(AccessGrantService::class)->approve($this->grant(), $this->actor());
                    } catch (Throwable $exception) {
                        $this->fail('Could not approve the grant', $exception);

                        return;
                    }

                    Notification::make()->title('Grant approved and active')->success()->send();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->grant()->state === AccessGrantState::Requested)
                ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->rows(2)
                        ->helperText('Recorded on the grant. The requester sees it.'),
                ])
                ->action(function (array $data): void {
                    try {
                        app(AccessGrantService::class)->reject(
                            $this->grant(),
                            $this->actor(),
                            (string) ($data['reason'] ?? ''),
                        );
                    } catch (Throwable $exception) {
                        $this->fail('Could not reject the grant', $exception);

                        return;
                    }

                    Notification::make()->title('Grant rejected')->success()->send();
                }),

            Action::make('revoke')
                ->label('Revoke')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke this grant?')
                ->modalDescription('Access ends immediately. The decision record and the review obligation stay.')
                ->visible(fn (): bool => in_array($this->grant()->state, [
                    AccessGrantState::Approved,
                    AccessGrantState::Active,
                ], true))
                ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->rows(2)
                        ->helperText('Why the access ended early. Required for the audit trail.'),
                ])
                ->action(function (array $data): void {
                    try {
                        app(AccessGrantService::class)->revoke(
                            $this->grant(),
                            $this->actor(),
                            (string) ($data['reason'] ?? ''),
                        );
                    } catch (Throwable $exception) {
                        $this->fail('Could not revoke the grant', $exception);

                        return;
                    }

                    Notification::make()->title('Grant revoked')->success()->send();
                }),

            Action::make('review')
                ->label('Record privacy review')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(fn (): bool => $this->grant()->review_required
                    && $this->grant()->review_outcome->isOpen())
                ->authorize(fn (): bool => auth()->user()?->canWrite() ?? false)
                ->schema([
                    Select::make('outcome')
                        ->label('Outcome')
                        ->options([
                            PrivacyReviewOutcome::Justified->value => 'Justified',
                            PrivacyReviewOutcome::NotJustified->value => 'Not justified',
                            PrivacyReviewOutcome::Escalated->value => 'Escalated',
                        ])
                        ->required(),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->helperText('What the reviewer checked. The decision is evidence; the notes explain it.'),
                ])
                ->action(function (array $data): void {
                    $outcome = PrivacyReviewOutcome::tryFrom((string) ($data['outcome'] ?? ''));

                    if ($outcome === null) {
                        Notification::make()->title('Choose an outcome')->danger()->send();

                        return;
                    }

                    try {
                        app(AccessGrantService::class)->recordReview(
                            $this->grant(),
                            $this->actor(),
                            $outcome,
                            $data['notes'] ?? null,
                        );
                    } catch (Throwable $exception) {
                        $this->fail('Could not record the review', $exception);

                        return;
                    }

                    Notification::make()->title('Privacy review recorded')->success()->send();
                }),
        ];
    }

    protected function grant(): AccessGrant
    {
        $record = $this->record;

        if (! $record instanceof AccessGrant) {
            throw new LogicException('The page record is not an access grant.');
        }

        return $record;
    }

    protected function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        return $user;
    }

    protected function fail(string $title, Throwable $exception): void
    {
        Notification::make()
            ->title($title)
            ->body($exception instanceof InvalidArgumentException || $exception instanceof LogicException
                ? $exception->getMessage()
                : 'Unexpected error. Nothing was changed.')
            ->danger()
            ->send();
    }
}
