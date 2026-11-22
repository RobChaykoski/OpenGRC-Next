<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EvidenceStreamResource\Pages\ListEvidenceStreams;
use App\Models\EvidenceStream;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Operational view of the signed evidence streams (ADR 0054).
 *
 * Creating a stream and showing its secret stays on the CLI: the secret is shown
 * once, and a screen is the wrong place to hand it out. This page is for seeing
 * whether a producer is healthy and for disabling one without a shell.
 */
class EvidenceStreamResource extends Resource
{
    protected static ?string $model = EvidenceStream::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 48;

    protected static ?string $navigationLabel = 'Evidence streams';

    protected static ?string $modelLabel = 'Evidence stream';

    protected static ?string $pluralModelLabel = 'Evidence streams';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('client_id')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('last_received_at')
                    ->label('Last accepted')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),

                TextColumn::make('last_cursor')
                    ->label('Last cursor')
                    ->fontFamily('mono')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('accepted_count')
                    ->label('Accepted')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('rejected_count')
                    ->label('Rejected')
                    ->numeric()
                    ->color(fn (EvidenceStream $record): string => $record->rejected_count > 0 ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('last_rejection_reason')
                    ->label('Last rejection')
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(),

                TextColumn::make('last_rejection_at')
                    ->label('Rejected at')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options(['1' => 'Active', '0' => 'Disabled']),
            ])
            ->recordActions([
                Action::make('disable')
                    ->label('Disable')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The producer will receive 403 until the stream is enabled again. The secret is unchanged; to rotate it, disable this stream and create a new one with opengrc:evidence:stream:create.')
                    ->visible(fn (EvidenceStream $record): bool => $record->is_active)
                    ->authorize(fn (EvidenceStream $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (EvidenceStream $record): void {
                        $record->forceFill(['is_active' => false])->save();

                        Notification::make()->title('Stream disabled')->success()->send();
                    }),

                Action::make('enable')
                    ->label('Enable')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (EvidenceStream $record): bool => ! $record->is_active)
                    ->authorize(fn (EvidenceStream $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (EvidenceStream $record): void {
                        $record->forceFill(['is_active' => true])->save();

                        Notification::make()->title('Stream enabled')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No evidence streams')
            ->emptyStateDescription('Create one with php artisan opengrc:evidence:stream:create --client=… --name=…; the secret is shown once.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvidenceStreams::route('/'),
        ];
    }
}
