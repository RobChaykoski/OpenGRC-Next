<?php

namespace App\Filament\Resources;

use App\Enums\ControlStatus;
use App\Filament\Resources\EvidenceRecordResource\Pages\ListEvidenceRecords;
use App\Filament\Resources\EvidenceRecordResource\Pages\ViewEvidenceRecord;
use App\Filament\Resources\EvidenceRecordResource\RelationManagers\ValidationsRelationManager;
use App\Models\EvidenceRecord;
use App\Services\Evidence\EvidenceHasher;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only evidence browser. The model is append-only, so the resource has no
 * create, edit, or delete pages and the policy denies those abilities outright.
 */
class EvidenceRecordResource extends Resource
{
    protected static ?string $model = EvidenceRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 36;

    protected static ?string $recordTitleAttribute = 'external_id';

    public static function getNavigationLabel(): string
    {
        return __('evidence.navigation.records');
    }

    public static function getNavigationGroup(): string
    {
        return __('evidence.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('evidence.model.record');
    }

    public static function getPluralModelLabel(): string
    {
        return __('evidence.model.records');
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['source', 'control.canonicalOutcomes', 'asset']))
            ->columns([
                TextColumn::make('source.name')
                    ->label(__('evidence.table.source'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('evidence_type')
                    ->label(__('evidence.table.evidence_type'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('control.code')
                    ->label(__('evidence.table.control'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('asset.name')
                    ->label(__('evidence.table.asset'))
                    ->placeholder('Fleet')
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('evidence.table.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('collected_at')
                    ->label(__('evidence.table.collected_at'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('payload_sha256')
                    ->label(__('evidence.table.hash'))
                    ->fontFamily('mono')
                    ->limit(12)
                    ->tooltip(fn (EvidenceRecord $record): string => (string) $record->payload_sha256)
                    ->copyable()
                    ->searchable(false)
                    ->toggleable(),

                TextColumn::make('external_id')
                    ->label(__('evidence.table.external_id'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('ingested_at')
                    ->label(__('evidence.table.ingested_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source_id')
                    ->label(__('evidence.filters.source'))
                    ->relationship('source', 'name')
                    ->searchable(),

                SelectFilter::make('evidence_type')
                    ->label(__('evidence.filters.evidence_type'))
                    ->options(fn (): array => EvidenceRecord::query()
                        ->distinct()
                        ->orderBy('evidence_type')
                        ->pluck('evidence_type', 'evidence_type')
                        ->all()),

                SelectFilter::make('control_id')
                    ->label(__('evidence.filters.control'))
                    ->relationship('control', 'code')
                    ->searchable(),

                SelectFilter::make('asset_id')
                    ->label(__('evidence.filters.asset'))
                    ->relationship('asset', 'name')
                    ->searchable(),

                SelectFilter::make('status')
                    ->label(__('evidence.filters.status'))
                    ->options(ControlStatus::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('collected_at', 'desc')
            ->emptyStateHeading(__('evidence.table.empty_heading'))
            ->emptyStateDescription(__('evidence.table.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('evidence.sections.measurement'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('source.name')
                            ->label(__('evidence.table.source'))
                            ->placeholder('—'),

                        TextEntry::make('evidence_type')
                            ->label(__('evidence.table.evidence_type'))
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('status')
                            ->label(__('evidence.table.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('external_id')
                            ->label(__('evidence.entries.external_id'))
                            ->fontFamily(FontFamily::Mono)
                            ->copyable(),

                        TextEntry::make('collected_at')
                            ->label(__('evidence.entries.collected_at'))
                            ->dateTime('M j, Y H:i'),

                        TextEntry::make('ingested_at')
                            ->label(__('evidence.entries.ingested_at'))
                            ->dateTime('M j, Y H:i'),
                    ]),

                Section::make(__('evidence.sections.mapping'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('control.code')
                            ->label(__('evidence.table.control'))
                            ->placeholder('—')
                            ->url(fn (EvidenceRecord $record): ?string => $record->control
                                ? ControlResource::getUrl('view', ['record' => $record->control])
                                : null),

                        TextEntry::make('asset.name')
                            ->label(__('evidence.table.asset'))
                            ->placeholder('Fleet'),

                        TextEntry::make('job.status')
                            ->label('Collection job')
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor())
                            ->placeholder('—'),

                        TextEntry::make('canonical_outcomes')
                            ->label(__('evidence.entries.canonical_outcomes'))
                            ->state(fn (EvidenceRecord $record): array => $record->control?->canonicalOutcomes->pluck('code')->all() ?? [])
                            ->badge()
                            ->placeholder(__('evidence.entries.canonical_outcomes_none'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('evidence.sections.integrity'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('payload_sha256')
                            ->label(__('evidence.entries.payload_hash'))
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('idempotency_key')
                            ->label(__('evidence.entries.idempotency_key'))
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('evidence.sections.payload'))
                    ->schema([
                        TextEntry::make('payload_json')
                            ->label('')
                            ->state(fn (EvidenceRecord $record): string => app(EvidenceHasher::class)
                                ->prettyJson(is_array($record->payload) ? $record->payload : []))
                            ->fontFamily(FontFamily::Mono)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap break-words text-xs']),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ValidationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvidenceRecords::route('/'),
            'view' => ViewEvidenceRecord::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['external_id', 'evidence_type', 'payload_sha256'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->evidence_type.' · '.$record->collected_at?->format('Y-m-d H:i');
    }
}
