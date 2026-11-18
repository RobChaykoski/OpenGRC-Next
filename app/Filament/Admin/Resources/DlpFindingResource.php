<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DlpFindingResource\Pages\ListDlpFindings;
use App\Models\DlpFinding;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only DLP review queue. Findings contain metadata and a content hash, not
 * the sensitive content itself.
 */
class DlpFindingResource extends Resource
{
    protected static ?string $model = DlpFinding::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 46;

    public static function getNavigationLabel(): string
    {
        return 'DLP findings';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ai' => 'info',
                        'upload' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'blocked' ? 'danger' : 'warning'),

                TextColumn::make('match_types')
                    ->label('Detected')
                    ->formatStateUsing(fn ($state): string => implode(', ', (array) $state))
                    ->wrap(),

                TextColumn::make('user.email')
                    ->label('Actor')
                    ->placeholder('System'),

                TextColumn::make('context_type')
                    ->label('Context')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('context_id')
                    ->label('Context ID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Source')
                    ->options([
                        'ai' => 'AI prompt',
                        'upload' => 'Upload',
                    ]),

                SelectFilter::make('severity')
                    ->label('Severity')
                    ->options([
                        'critical' => 'Critical',
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ]),

                SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'blocked' => 'Blocked',
                        'flagged' => 'Flagged',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No DLP findings')
            ->emptyStateDescription('Blocked and flagged AI prompts and uploads will appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDlpFindings::route('/'),
        ];
    }
}
