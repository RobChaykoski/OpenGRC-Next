<?php

namespace App\Filament\Resources\SbomDocumentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $title = 'Components';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'vulnerabilities as open_vulnerabilities_count' => fn (Builder $vulnerabilities): Builder => $vulnerabilities->where('status', 'open'),
            ]))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Component')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('version')
                    ->label('Version')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ecosystem')
                    ->label('Ecosystem')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('license')
                    ->label('License')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('purl')
                    ->label('purl')
                    ->limit(50)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('open_vulnerabilities_count')
                    ->label('Open advisories')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),

                TextColumn::make('supplier')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100]);
    }
}
