<?php

namespace App\Filament\Resources;

use App\Enums\PolicyExceptionStatus;
use App\Filament\Resources\PolicyExceptionResource\Pages\ListPolicyExceptions;
use App\Filament\Resources\PolicyExceptionResource\Pages\ViewPolicyException;
use App\Models\PolicyException;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Global exception register.
 *
 * Policy exceptions are created on the policy that owns the requirement. This
 * resource is the cross-policy view an approver, auditor or programme lead needs:
 * status, expiry and compensating controls in one place. It is deliberately
 * read-only; creating or editing an exception stays on the policy.
 */
class PolicyExceptionResource extends Resource
{
    protected static ?string $model = PolicyException::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Exceptions';

    protected static ?string $modelLabel = 'Exception';

    protected static ?string $pluralModelLabel = 'Exceptions';

    protected static ?string $recordTitleAttribute = 'name';

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'policy',
                'requester',
                'approver',
            ]))
            ->defaultSort('expiration_date')
            ->columns([
                TextColumn::make('name')
                    ->label('Exception')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('policy.name')
                    ->label('Policy')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('expiry')
                    ->label('Expiry')
                    ->getStateUsing(fn (PolicyException $record): string => self::expiryLabel($record))
                    ->badge()
                    ->color(fn (PolicyException $record): string => self::expiryColor($record)),

                TextColumn::make('effective_date')
                    ->label('Effective')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expiration_date')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiration'),

                TextColumn::make('requester.name')
                    ->label('Requested by')
                    ->formatStateUsing(fn (PolicyException $record): string => $record->requester?->displayName() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approver.name')
                    ->label('Approved by')
                    ->formatStateUsing(fn (PolicyException $record): string => $record->approver?->displayName() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PolicyExceptionStatus::class),

                SelectFilter::make('policy_id')
                    ->label('Policy')
                    ->relationship('policy', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('expiry')
                    ->label('Expiry')
                    ->options([
                        'expiring' => 'Expiring in 30 days',
                        'expired' => 'Expired',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'expiring' => $query
                                ->where('status', PolicyExceptionStatus::Approved)
                                ->whereBetween('expiration_date', [today(), today()->addDays(30)]),
                            'expired' => $query
                                ->where('expiration_date', '<', today()),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No exceptions')
            ->emptyStateDescription('Policy exceptions appear here after they are requested on a policy.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Exception')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Exception')
                            ->columnSpanFull(),

                        TextEntry::make('policy.name')
                            ->label('Policy'),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('expiry')
                            ->label('Expiry')
                            ->state(fn (PolicyException $record): string => self::expiryLabel($record))
                            ->badge()
                            ->color(fn (PolicyException $record): string => self::expiryColor($record)),

                        TextEntry::make('requested_date')
                            ->label('Requested')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('effective_date')
                            ->label('Effective')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('expiration_date')
                            ->label('Expires')
                            ->date()
                            ->placeholder('No expiration'),

                        TextEntry::make('requester.name')
                            ->label('Requested by')
                            ->state(fn (PolicyException $record): string => $record->requester?->displayName() ?? '—'),

                        TextEntry::make('approver.name')
                            ->label('Approved by')
                            ->state(fn (PolicyException $record): string => $record->approver?->displayName() ?? '—'),
                    ]),

                Section::make('Justification and risk')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('justification')
                            ->label('Business justification')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('risk_assessment')
                            ->label('Risk assessment')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('compensating_controls')
                            ->label('Compensating controls')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Audit')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('creator.name')
                            ->label('Created by')
                            ->placeholder('—'),

                        TextEntry::make('updater.name')
                            ->label('Updated by')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolicyExceptions::route('/'),
            'view' => ViewPolicyException::route('/{record}'),
        ];
    }

    public static function expiryLabel(PolicyException $exception): string
    {
        if ($exception->expiration_date === null) {
            return 'No expiry';
        }

        if ($exception->expiration_date->lt(today())) {
            return 'Expired';
        }

        $days = (int) today()->diffInDays($exception->expiration_date);

        return $days === 0 ? 'Expires today' : "Expires in {$days} day(s)";
    }

    public static function expiryColor(PolicyException $exception): string
    {
        if ($exception->expiration_date === null) {
            return 'gray';
        }

        if ($exception->expiration_date->lt(today())) {
            return 'danger';
        }

        $days = (int) today()->diffInDays($exception->expiration_date);

        return $days <= 30 ? 'warning' : 'success';
    }
}
