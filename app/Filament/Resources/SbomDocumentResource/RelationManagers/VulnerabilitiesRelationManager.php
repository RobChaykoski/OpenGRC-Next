<?php

namespace App\Filament\Resources\SbomDocumentResource\RelationManagers;

use App\Enums\EvidenceSeverity;
use App\Enums\SoftwareVulnerabilityStatus;
use App\Filament\Resources\FindingResource;
use App\Models\SoftwareVulnerability;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VulnerabilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'vulnerabilities';

    protected static ?string $title = 'Vulnerabilities';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['component', 'finding']))
            ->recordTitleAttribute('osv_id')
            ->columns([
                TextColumn::make('osv_id')
                    ->label('OSV')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('component.name')
                    ->label('Component')
                    ->formatStateUsing(fn (string $state, SoftwareVulnerability $record): string => $record->component?->displayName() ?? $state)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('severity')
                    ->badge()
                    ->sortable(),

                TextColumn::make('fixed_version')
                    ->label('Fixed in')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('finding_id')
                    ->label('Finding')
                    ->formatStateUsing(fn (SoftwareVulnerability $record): string => $record->finding?->reference() ?? '—')
                    ->url(fn (SoftwareVulnerability $record): ?string => $record->finding_id === null
                        ? null
                        : FindingResource::getUrl('view', ['record' => $record->finding_id]))
                    ->openUrlInNewTab(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options(EvidenceSeverity::class),

                SelectFilter::make('status')
                    ->options(SoftwareVulnerabilityStatus::class),
            ])
            ->defaultSort('severity')
            ->paginated([25, 50, 100]);
    }
}
