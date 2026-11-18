<?php

namespace App\Filament\Resources\ControlResource\RelationManagers;

use App\Filament\Resources\EvidenceRecordResource;
use App\Models\EvidenceRecord;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The control-and-evidence matrix for one control. Read-only: evidence is
 * append-only and is opened in the evidence browser for its full payload.
 */
class EvidenceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'evidenceRecords';

    public static ?string $title = 'Evidence records';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['source', 'asset']))
            ->columns([
                TextColumn::make('source.name')
                    ->label(__('evidence.table.source'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('evidence_type')
                    ->label(__('evidence.table.evidence_type'))
                    ->searchable(),

                TextColumn::make('asset.name')
                    ->label(__('evidence.table.asset'))
                    ->placeholder('Fleet')
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('evidence.table.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false),

                TextColumn::make('collected_at')
                    ->label(__('evidence.table.collected_at'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('payload_sha256')
                    ->label(__('evidence.table.hash'))
                    ->fontFamily('mono')
                    ->limit(12)
                    ->copyable()
                    ->searchable(false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (EvidenceRecord $record): string => EvidenceRecordResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('collected_at', 'desc')
            ->emptyStateHeading(__('evidence.table.empty_heading'))
            ->emptyStateDescription('Machine evidence mapped to this control will appear here.');
    }
}
