<?php

namespace App\Filament\Resources;

use App\Enums\AccessAction;
use App\Enums\AccessReason;
use App\Enums\DocumentClassification;
use App\Filament\Resources\AccessDecisionResource\Pages\ListAccessDecisions;
use App\Filament\Resources\AccessDecisionResource\Pages\ViewAccessDecision;
use App\Models\AccessDecision;
use BackedEnum;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Append-only evidence of runtime access decisions (ADR 0049).
 *
 * Read-only for every role: an administrator can inspect a decision, not edit
 * it. A correction is a new decision, never an update to an old one.
 */
class AccessDecisionResource extends Resource
{
    protected static ?string $model = AccessDecision::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Access governance';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Access decisions';

    protected static ?string $modelLabel = 'Access decision';

    protected static ?string $pluralModelLabel = 'Access decisions';

    protected static ?string $recordTitleAttribute = 'correlation_id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
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
            ->modifyQueryUsing(fn ($query) => $query->with('policy'))
            ->defaultSort('decided_at', 'desc')
            ->columns([
                TextColumn::make('decided_at')
                    ->label('Decided')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('subject_label')
                    ->label('Subject')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                TextColumn::make('resource_ref')
                    ->label('Resource')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                TextColumn::make('tier')
                    ->label('Level')
                    ->badge()
                    ->sortable(),

                TextColumn::make('action')
                    ->badge()
                    ->sortable(),

                TextColumn::make('reason')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('policy.code')
                    ->label('Policy')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('risk_weight')
                    ->label('Weight')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('pep')
                    ->label('Enforcement point')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(AccessAction::class),

                SelectFilter::make('reason')
                    ->options(AccessReason::class),

                SelectFilter::make('tier')
                    ->label('Level')
                    ->options(DocumentClassification::class),
            ])
            ->emptyStateHeading('No access decisions')
            ->emptyStateDescription('Decisions appear here when an enforcement point submits a request and records the outcome.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Decision')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('decided_at')
                            ->label('Decided')
                            ->dateTime(),

                        TextEntry::make('subject_label')
                            ->label('Subject'),

                        TextEntry::make('subject_type')
                            ->label('Subject type'),

                        TextEntry::make('resource_ref')
                            ->label('Resource')
                            ->columnSpan(2),

                        TextEntry::make('tier')
                            ->label('Level')
                            ->badge(),

                        TextEntry::make('action')
                            ->badge(),

                        TextEntry::make('reason')
                            ->badge(),

                        TextEntry::make('policy.code')
                            ->label('Matched policy')
                            ->placeholder('No policy matched'),

                        TextEntry::make('policy_version')
                            ->label('Policy version')
                            ->placeholder('—'),

                        TextEntry::make('ttl_seconds')
                            ->label('Time to live')
                            ->suffix(' seconds')
                            ->placeholder('—'),

                        TextEntry::make('risk_weight')
                            ->label('Exposure weight')
                            ->numeric(),

                        TextEntry::make('pep')
                            ->label('Enforcement point')
                            ->placeholder('—'),

                        TextEntry::make('latency_ms')
                            ->label('Evaluation time')
                            ->suffix(' ms')
                            ->placeholder('—'),
                    ]),

                Section::make('Asserted context')
                    ->schema([
                        KeyValueEntry::make('context')
                            ->label('Context snapshot')
                            ->placeholder('No context asserted'),
                    ]),

                Section::make('Integrity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('correlation_id')
                            ->label('Correlation id')
                            ->fontFamily('mono')
                            ->copyable(),

                        TextEntry::make('rules_hash')
                            ->label('Rule hash')
                            ->fontFamily('mono')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('previous_hash')
                            ->label('Previous hash')
                            ->fontFamily('mono')
                            ->copyable()
                            ->placeholder('Genesis'),

                        TextEntry::make('entry_hash')
                            ->label('Entry hash')
                            ->fontFamily('mono')
                            ->copyable(),

                        TextEntry::make('chain_note')
                            ->label('')
                            ->state('This record is append-only. A correction is a new decision, never an edit.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessDecisions::route('/'),
            'view' => ViewAccessDecision::route('/{record}'),
        ];
    }
}
