<?php

namespace App\Filament\Resources\EvidenceRecordResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only history of the rules that evaluated this evidence.
 */
class ValidationsRelationManager extends RelationManager
{
    protected static string $relationship = 'validations';

    public static ?string $title = 'Validation history';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset']))
            ->columns([
                TextColumn::make('status')
                    ->label(__('evidence.validation.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor()),

                TextColumn::make('asset.name')
                    ->label(__('evidence.validation.asset'))
                    ->placeholder('Fleet'),

                TextColumn::make('asset_identifier')
                    ->label(__('evidence.validation.asset_identifier'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('rule_version')
                    ->label(__('evidence.validation.rule_version'))
                    ->placeholder('—'),

                TextColumn::make('result.drift')
                    ->label(__('evidence.validation.drift'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No')
                    ->color(fn ($state): string => $state ? 'warning' : 'gray')
                    ->searchable(false),

                TextColumn::make('result.previous_status')
                    ->label(__('evidence.validation.previous'))
                    ->badge()
                    ->placeholder('—')
                    ->searchable(false),

                TextColumn::make('evaluated_at')
                    ->label(__('evidence.validation.evaluated_at'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('evaluated_at', 'desc')
            ->emptyStateHeading(__('evidence.validation.empty_heading'))
            ->emptyStateDescription(__('evidence.validation.empty_description'));
    }
}
