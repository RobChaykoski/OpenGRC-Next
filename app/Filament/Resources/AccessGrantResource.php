<?php

namespace App\Filament\Resources;

use App\Enums\AccessGrantState;
use App\Enums\PrivacyReviewOutcome;
use App\Filament\Resources\AccessGrantResource\Pages\CreateAccessGrant;
use App\Filament\Resources\AccessGrantResource\Pages\ListAccessGrants;
use App\Filament\Resources\AccessGrantResource\Pages\ViewAccessGrant;
use App\Models\AccessGrant;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Just-in-time access grants (ADR 0049).
 *
 * A grant is the entitlement and its clock. Break-glass grants carry a reason
 * code and a privacy review with an owner and a due date.
 */
class AccessGrantResource extends Resource
{
    protected static ?string $model = AccessGrant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Access governance';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Access grants';

    protected static ?string $modelLabel = 'Access grant';

    protected static ?string $pluralModelLabel = 'Access grants';

    protected static ?string $recordTitleAttribute = 'subject_label';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Subject and resource')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('subject_type')
                            ->label('Subject type')
                            ->options([
                                'user' => 'Person',
                                'machine' => 'Machine identity',
                                'role' => 'Role',
                            ])
                            ->default('user')
                            ->required(),

                        Select::make('subject_id')
                            ->label('Person')
                            ->relationship('subject', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get): bool => $get('subject_type') === 'user'),

                        TextInput::make('subject_label')
                            ->label('Subject')
                            ->required()
                            ->maxLength(191)
                            ->helperText('Name or service identifier, as it will appear in the decision record.'),

                        Select::make('protected_resource_id')
                            ->label('Protected resource')
                            ->relationship('resource', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('purpose_code')
                            ->label('Purpose')
                            ->options([
                                'care' => 'Care delivery',
                                'research' => 'Research',
                                'analytics' => 'Analytics',
                                'operations' => 'Operations',
                                'incident' => 'Incident response',
                                'legal' => 'Legal or audit',
                            ])
                            ->default('care')
                            ->required()
                            ->helperText('Purpose is a decision input, not a label.'),

                        Select::make('risk_id')
                            ->label('Linked risk')
                            ->relationship('risk', 'name')
                            ->searchable()
                            ->helperText('Optional. Links the grant to the risk it is meant to control.'),
                    ]),

                Section::make('Justification')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Textarea::make('justification')
                            ->required()
                            ->rows(3)
                            ->helperText('Why this access is necessary now. No patient or customer data.')
                            ->columnSpanFull(),

                        Toggle::make('is_break_glass')
                            ->label('Break-the-glass')
                            ->helperText('Emergency access. Requires a reason code and creates a privacy review.'),

                        Select::make('clinical_reason')
                            ->label('Emergency reason')
                            ->options([
                                'trauma' => 'Trauma',
                                'cardiac' => 'Cardiac',
                                'unresponsive' => 'Unresponsive patient',
                                'safeguarding' => 'Safeguarding',
                                'public_safety' => 'Public safety',
                                'other' => 'Other',
                            ])
                            ->visible(fn ($get): bool => (bool) $get('is_break_glass')),

                        DateTimePicker::make('expires_at')
                            ->label('Requested until')
                            ->seconds(false)
                            ->helperText('Capped by the configured maximum. Break-glass is capped at the shorter limit.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['resource', 'approver']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (AccessGrant $record): string => $record->reference())
                    ->fontFamily('mono'),

                TextColumn::make('subject_label')
                    ->label('Subject')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                TextColumn::make('resource.name')
                    ->label('Resource')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(40),

                TextColumn::make('state')
                    ->badge()
                    ->sortable(),

                TextColumn::make('is_break_glass')
                    ->label('Break-glass')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Break-glass' : 'Standard')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (AccessGrant $record): string => $record->isExpired() ? 'danger' : 'gray'),

                TextColumn::make('review_outcome')
                    ->label('Review')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('review_due_at')
                    ->label('Review due')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (AccessGrant $record): string => $record->reviewIsOverdue() ? 'danger' : 'gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->options(AccessGrantState::class),

                SelectFilter::make('is_break_glass')
                    ->label('Break-glass')
                    ->options([
                        '1' => 'Break-glass',
                        '0' => 'Standard',
                    ]),

                SelectFilter::make('review')
                    ->options([
                        'overdue' => 'Review overdue',
                        'open' => 'Review pending',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'overdue' => $query->where('review_required', true)
                            ->where('review_outcome', PrivacyReviewOutcome::Pending->value)
                            ->whereNotNull('review_due_at')
                            ->whereDate('review_due_at', '<', today()),
                        'open' => $query->where('review_required', true)
                            ->where('review_outcome', PrivacyReviewOutcome::Pending->value),
                        default => $query,
                    }),
            ])
            ->emptyStateHeading('No access grants')
            ->emptyStateDescription('Request just-in-time access with a justification, an expiry and an approver.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Grant')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subject_label')
                            ->label('Subject')
                            ->columnSpan(2),

                        TextEntry::make('state')
                            ->badge(),

                        TextEntry::make('resource.name')
                            ->label('Protected resource')
                            ->placeholder('—'),

                        TextEntry::make('purpose_code')
                            ->label('Purpose'),

                        TextEntry::make('is_break_glass')
                            ->label('Type')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Break-the-glass' : 'Standard'),

                        TextEntry::make('clinical_reason')
                            ->label('Emergency reason')
                            ->placeholder('—'),

                        TextEntry::make('justification')
                            ->columnSpanFull(),

                        TextEntry::make('requested_at')
                            ->label('Requested')
                            ->dateTime(),

                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->dateTime(),

                        TextEntry::make('remaining')
                            ->label('Remaining')
                            ->state(fn (AccessGrant $record): string => $record->remainingMinutes() === null
                                ? '—'
                                : $record->remainingMinutes().' minutes'),

                        TextEntry::make('requester.name')
                            ->label('Requested by')
                            ->placeholder('—'),

                        TextEntry::make('approver.name')
                            ->label('Approved by')
                            ->placeholder('Not approved'),

                        TextEntry::make('revoker.name')
                            ->label('Revoked by')
                            ->placeholder('—'),

                        TextEntry::make('revoked_reason')
                            ->label('Revocation reason')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('risk.name')
                            ->label('Linked risk')
                            ->placeholder('—'),
                    ]),

                Section::make('Privacy review')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('review_outcome')
                            ->label('Outcome')
                            ->badge(),

                        TextEntry::make('review_due_at')
                            ->label('Due')
                            ->date()
                            ->placeholder('No review obligation')
                            ->color(fn (AccessGrant $record): string => $record->reviewIsOverdue() ? 'danger' : 'gray'),

                        TextEntry::make('reviewer.name')
                            ->label('Reviewed by')
                            ->placeholder('—'),

                        TextEntry::make('reviewed_at')
                            ->label('Reviewed at')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('review_notes')
                            ->label('Review notes')
                            ->placeholder('—')
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessGrants::route('/'),
            'create' => CreateAccessGrant::route('/create'),
            'view' => ViewAccessGrant::route('/{record}'),
        ];
    }
}
