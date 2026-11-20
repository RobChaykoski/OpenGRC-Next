<?php

namespace App\Filament\Resources\ImplementationResource\RelationManagers;

use App\Enums\ControlAttestationStatus;
use App\Models\ControlAttestation;
use App\Models\User;
use App\Services\Controls\ControlAttestationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

/**
 * The control owner's confirmation history for one implementation.
 *
 * A request is pending until the owner or an admin answers. Expiry does not
 * rewrite the statement; it records that no answer arrived in time.
 */
class AttestationsRelationManager extends RelationManager
{
    protected static string $relationship = 'attestations';

    protected static ?string $title = 'Attestations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('due_at')
                    ->label('Response due')
                    ->default(today()->addDays(14))
                    ->required(),
                Textarea::make('request_note')
                    ->label('What should the owner confirm?')
                    ->helperText('A specific statement is easier to attest than "is this still true?".')
                    ->rows(3)
                    ->columnSpanFull(),
                Hidden::make('requested_by')
                    ->default(fn (): ?int => auth()->id()),
                Hidden::make('requested_at')
                    ->default(fn () => now()),
                Hidden::make('status')
                    ->default(ControlAttestationStatus::Pending->value),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (ControlAttestation $record): string => $record->reference())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime('M j, Y')
                    ->sortable(),

                TextColumn::make('due_at')
                    ->label('Due')
                    ->date()
                    ->sortable()
                    ->color(fn (ControlAttestation $record): string => $record->isOverdue() ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?ControlAttestationStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?ControlAttestationStatus $state): string|array|null => $state?->getColor()),

                TextColumn::make('request_note')
                    ->label('Request')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('attestedBy.name')
                    ->label('Answered by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('attested_at')
                    ->label('Answered')
                    ->dateTime('M j, Y')
                    ->placeholder('—'),

                TextColumn::make('statement')
                    ->label('Statement')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ControlAttestationStatus::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Request attestation')
                    ->disabled(fn (): bool => ControlAttestation::query()
                        ->where('implementation_id', $this->getOwnerRecord()->getKey())
                        ->pending()
                        ->exists()),
            ])
            ->recordActions([
                Action::make('attest')
                    ->label('Attest')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ControlAttestation $record): bool => $record->status->isOpen() && $this->canRespond($record))
                    ->schema([
                        Textarea::make('statement')
                            ->label('Statement')
                            ->helperText('Confirm what is true, and note any caveat.')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ControlAttestation $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        try {
                            app(ControlAttestationService::class)->attest($record, $user, (string) $data['statement']);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Attestation recorded')->success()->send();
                    }),

                Action::make('decline')
                    ->label('Decline')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ControlAttestation $record): bool => $record->status->isOpen() && $this->canRespond($record))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->helperText('Why the statement cannot be confirmed.')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ControlAttestation $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        try {
                            app(ControlAttestationService::class)->decline($record, $user, (string) $data['reason']);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Attestation declined')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('No attestations')
            ->emptyStateDescription('Request a dated confirmation from the implementation owner.');
    }

    protected function canRespond(ControlAttestation $record): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin()
            || (int) $record->implementation?->implementation_owner_id === (int) $user->getKey();
    }
}
