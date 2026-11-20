<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DlpDisposition;
use App\Filament\Admin\Resources\DlpFindingResource\Pages\ListDlpFindings;
use App\Models\DlpFinding;
use App\Services\Dlp\DlpFindingReview;
use App\Services\Dlp\DlpIdentifierCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'reviewer']))
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

                TextColumn::make('disposition')
                    ->label('Review')
                    ->badge()
                    ->placeholder('Unreviewed')
                    ->formatStateUsing(fn (?DlpDisposition $state): ?string => $state?->getLabel())
                    ->color(fn (?DlpDisposition $state): string|array|null => $state?->getColor())
                    ->sortable(),

                TextColumn::make('reviewer.name')
                    ->label('Reviewed by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—')
                    ->sortable(),

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

                SelectFilter::make('disposition')
                    ->label('Review')
                    ->options(array_merge(
                        collect(DlpDisposition::cases())
                            ->mapWithKeys(fn (DlpDisposition $case): array => [$case->value => $case->getLabel()])
                            ->all(),
                        ['unreviewed' => 'Unreviewed'],
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === 'unreviewed') {
                            return $query->whereNull('disposition');
                        }

                        return is_string($value) && $value !== ''
                            ? $query->where('disposition', $value)
                            : $query;
                    }),

                SelectFilter::make('identifier_type')
                    ->label('Identifier type')
                    ->options(fn (): array => app(DlpIdentifierCatalog::class)->options())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        $labels = app(DlpIdentifierCatalog::class)->labelsFor($value);

                        return $query->where(function (Builder $query) use ($value, $labels): void {
                            $query->whereJsonContains('identifier_types', $value);

                            foreach ($labels as $label) {
                                $query->orWhereJsonContains('match_types', $label);
                            }
                        });
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->authorize(fn (DlpFinding $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([
                        Select::make('disposition')
                            ->label('Outcome')
                            ->options(DlpDisposition::class)
                            ->required(),
                        Textarea::make('review_notes')
                            ->label('Review notes')
                            ->helperText('Why the detector was right or wrong. Do not paste the sensitive value.')
                            ->rows(3),
                    ])
                    ->fillForm(fn (DlpFinding $record): array => [
                        'disposition' => $record->disposition?->value,
                        'review_notes' => $record->review_notes,
                    ])
                    ->action(function (DlpFinding $record, array $data): void {
                        app(DlpFindingReview::class)->record(
                            $record,
                            DlpDisposition::from((string) $data['disposition']),
                            $data['review_notes'] ?? null,
                        );

                        Notification::make()
                            ->title('DLP finding reviewed')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Review DLP finding')
                    ->modalDescription('Record whether the detector was right. The content is not shown; use the source record and the hash if you need to inspect it.'),
            ])
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
