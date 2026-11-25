<?php

namespace App\Filament\Resources\ModelArtifactResource\RelationManagers;

use App\Filament\Resources\FindingResource;
use App\Models\Finding;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Findings raised by the registrar. The finding is the response; deleting the
 * artifact does not resolve it.
 */
class FindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'findings';

    protected static ?string $title = 'Findings';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Reference')
                    ->formatStateUsing(fn (Finding $record): string => $record->reference())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->wrap()
                    ->limit(60)
                    ->url(fn (Finding $record): string => FindingResource::getUrl('view', ['record' => $record])),

                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor()),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor()),

                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->placeholder('—'),
            ])
            ->paginated([10, 25, 50]);
    }
}
