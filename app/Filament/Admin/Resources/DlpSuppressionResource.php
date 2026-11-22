<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DlpSuppressionState;
use App\Filament\Admin\Resources\DlpSuppressionResource\Pages\CreateDlpSuppression;
use App\Filament\Admin\Resources\DlpSuppressionResource\Pages\ListDlpSuppressions;
use App\Models\DlpSuppression;
use App\Models\User;
use App\Services\Dlp\DlpIdentifierCatalog;
use App\Services\Dlp\DlpSuppressionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Time-boxed DLP suppressions (ADR 0053).
 *
 * A suppression accepts a detector's output for a bounded scope and period. It
 * is requested, approved by someone else, and expires. It never hides a
 * finding: the finding stays with its suppression visible.
 */
class DlpSuppressionResource extends Resource
{
    protected static ?string $model = DlpSuppression::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-slash';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 45;

    protected static ?string $navigationLabel = 'DLP suppressions';

    protected static ?string $modelLabel = 'DLP suppression';

    protected static ?string $pluralModelLabel = 'DLP suppressions';

    protected static ?string $recordTitleAttribute = 'justification';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Scope')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('dlp_policy_id')
                            ->label('Policy')
                            ->relationship('policy', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Suppression')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('identifier_type')
                            ->label('Identifier type')
                            ->options(fn (): array => app(DlpIdentifierCatalog::class)->options())
                            ->helperText('Optional. Leave empty for every identifier type in the policy.'),

                        TextInput::make('match_type')
                            ->label('Match label')
                            ->maxLength(120)
                            ->helperText('Optional. The scanner label to accept, exactly as it appears on the finding.'),

                        Textarea::make('justification')
                            ->required()
                            ->rows(3)
                            ->helperText('Why this output is acceptable. No patient or customer data.')
                            ->columnSpanFull(),

                        DateTimePicker::make('expires_at')
                            ->label('Expires')
                            ->required()
                            ->seconds(false)
                            ->minDate(now())
                            ->helperText('Required, and capped at one year. Continuing the acceptance is a new request.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['policy', 'requester', 'approver']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->state(fn (DlpSuppression $record): string => $record->reference())
                    ->fontFamily('mono'),

                TextColumn::make('policy.name')
                    ->label('Policy')
                    ->wrap()
                    ->limit(40),

                TextColumn::make('scope')
                    ->label('Scope')
                    ->state(fn (DlpSuppression $record): string => collect([
                        $record->identifier_type,
                        $record->match_type,
                    ])->filter()->implode(' · ') ?: 'Whole policy'),

                TextColumn::make('state')
                    ->badge()
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label('Requested by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('approver.name')
                    ->label('Approved by')
                    ->placeholder('Not approved')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->options(DlpSuppressionState::class),

                SelectFilter::make('dlp_policy_id')
                    ->label('Policy')
                    ->relationship('policy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Approve this suppression for its scope and period? The approver cannot be the requester.')
                    ->visible(fn (DlpSuppression $record): bool => $record->state === DlpSuppressionState::Requested)
                    ->authorize(fn (DlpSuppression $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (DlpSuppression $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(DlpSuppressionService::class)->approve($record, $user);

                        Notification::make()->title('Suppression approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (DlpSuppression $record): bool => $record->state === DlpSuppressionState::Requested)
                    ->authorize(fn (DlpSuppression $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([
                        Textarea::make('review_notes')
                            ->label('Reason')
                            ->rows(3)
                            ->helperText('Why the acceptance was refused.'),
                    ])
                    ->action(function (DlpSuppression $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(DlpSuppressionService::class)->reject(
                            $record,
                            $user,
                            $data['review_notes'] ?? null,
                        );

                        Notification::make()->title('Suppression rejected')->success()->send();
                    }),

                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (DlpSuppression $record): bool => $record->state->isOpen())
                    ->authorize(fn (DlpSuppression $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([
                        Textarea::make('revoked_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3)
                            ->helperText('Why the acceptance ends early.'),
                    ])
                    ->action(function (DlpSuppression $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(DlpSuppressionService::class)->revoke(
                            $record,
                            $user,
                            (string) $data['revoked_reason'],
                        );

                        Notification::make()->title('Suppression revoked')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No DLP suppressions')
            ->emptyStateDescription('Suppress a detector for a bounded scope and period, with a reason and an approver.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDlpSuppressions::route('/'),
            'create' => CreateDlpSuppression::route('/create'),
        ];
    }
}
