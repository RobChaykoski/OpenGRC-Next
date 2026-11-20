<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PolicyAcknowledgementResource\Pages\ListPolicyAcknowledgements;
use App\Filament\Resources\PolicyAcknowledgementResource\Pages\ViewPolicyAcknowledgement;
use App\Models\PolicyAcknowledgement;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only coverage view of policy acknowledgements.
 *
 * Users acknowledge on the My policies page; this register lets an owner or
 * auditor see who acknowledged which revision and what is stale.
 */
class PolicyAcknowledgementResource extends Resource
{
    protected static ?string $model = PolicyAcknowledgement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 46;

    protected static ?string $navigationLabel = 'Policy acknowledgements';

    protected static ?string $modelLabel = 'Policy acknowledgement';

    protected static ?string $pluralModelLabel = 'Policy acknowledgements';

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['policy', 'user']))
            ->defaultSort('acknowledged_at', 'desc')
            ->columns([
                TextColumn::make('policy.name')
                    ->label('Policy')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),

                TextColumn::make('acknowledged_at')
                    ->label('Acknowledged')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('current')
                    ->label('Revision')
                    ->getStateUsing(fn (PolicyAcknowledgement $record): string => $record->isCurrent() ? 'Current' : 'Stale')
                    ->badge()
                    ->color(fn (PolicyAcknowledgement $record): string => $record->isCurrent() ? 'success' : 'warning'),

                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('policy_id')
                    ->label('Policy')
                    ->relationship('policy', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->emptyStateHeading('No policy acknowledgements')
            ->emptyStateDescription('Acknowledgements appear here after a user confirms a policy on My policies.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Acknowledgement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('policy.name')
                            ->label('Policy')
                            ->columnSpanFull(),

                        TextEntry::make('user.name')
                            ->label('User'),

                        TextEntry::make('acknowledged_at')
                            ->label('Acknowledged')
                            ->dateTime(),

                        TextEntry::make('method')
                            ->label('Method')
                            ->badge(),

                        TextEntry::make('current')
                            ->label('Revision')
                            ->state(fn (PolicyAcknowledgement $record): string => $record->isCurrent() ? 'Current' : 'Stale')
                            ->badge()
                            ->color(fn (PolicyAcknowledgement $record): string => $record->isCurrent() ? 'success' : 'warning'),

                        TextEntry::make('policy_updated_at')
                            ->label('Policy updated at')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolicyAcknowledgements::route('/'),
            'view' => ViewPolicyAcknowledgement::route('/{record}'),
        ];
    }
}
