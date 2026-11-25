<?php

namespace App\Filament\Resources;

use App\Enums\ModelArtifactFormat;
use App\Enums\ModelArtifactStatus;
use App\Filament\Resources\ModelArtifactResource\Pages\ListModelArtifacts;
use App\Filament\Resources\ModelArtifactResource\Pages\ViewModelArtifact;
use App\Filament\Resources\ModelArtifactResource\RelationManagers\FilesRelationManager;
use App\Filament\Resources\ModelArtifactResource\RelationManagers\FindingsRelationManager;
use App\Models\ModelArtifact;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Read-only register of AI model artifacts (ADR 0059).
 *
 * Registration stays on the CLI because the command walks and hashes a path on
 * the server. The screen shows what was registered, lets an editor record the
 * dated approval, and withdraws it when the artifact changes. It never shows a
 * weight, tokenizer vocabulary, prompt template body, or secret.
 */
class ModelArtifactResource extends Resource
{
    protected static ?string $model = ModelArtifact::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 39;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('model_artifact.navigation.label');
    }

    public static function getModelLabel(): string
    {
        return __('model_artifact.model.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('model_artifact.model.plural_label');
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['owner', 'asset', 'application']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('model_artifact.table.name'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('format')
                    ->label(__('model_artifact.table.format'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('model_artifact.table.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('provenance_status')
                    ->label(__('model_artifact.table.provenance'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('model_artifact.provenance.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'partial' => 'warning',
                        default => 'danger',
                    })
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('architecture')
                    ->label(__('model_artifact.table.architecture'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('quantization')
                    ->label(__('model_artifact.table.quantization'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('file_count')
                    ->label(__('model_artifact.table.files'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_bytes')
                    ->label(__('model_artifact.table.size'))
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('license')
                    ->label(__('model_artifact.table.license'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('asset.name')
                    ->label(__('model_artifact.table.asset'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('owner.name')
                    ->label(__('model_artifact.table.owner'))
                    ->placeholder(__('model_artifact.unassigned'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label(__('model_artifact.table.approved_at'))
                    ->dateTime('M j, Y')
                    ->placeholder(__('model_artifact.not_approved'))
                    ->sortable(),

                TextColumn::make('last_scanned_at')
                    ->label(__('model_artifact.table.last_scanned_at'))
                    ->since()
                    ->placeholder(__('model_artifact.never_scanned'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('format')
                    ->label(__('model_artifact.filters.format'))
                    ->options(ModelArtifactFormat::class)
                    ->multiple(),

                SelectFilter::make('status')
                    ->label(__('model_artifact.filters.status'))
                    ->options(ModelArtifactStatus::class)
                    ->multiple(),

                SelectFilter::make('provenance_status')
                    ->label(__('model_artifact.filters.provenance'))
                    ->options([
                        'verified' => __('model_artifact.provenance.verified'),
                        'partial' => __('model_artifact.provenance.partial'),
                        'unverified' => __('model_artifact.provenance.unverified'),
                    ]),

                SelectFilter::make('owner_id')
                    ->label(__('model_artifact.filters.owner'))
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('asset_id')
                    ->label(__('model_artifact.filters.asset'))
                    ->relationship('asset', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('needs_review')
                    ->label(__('model_artifact.filters.needs_review'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                        $inner->where('status', ModelArtifactStatus::Proposed->value)
                            ->orWhereNull('provenance_verified_at')
                            ->orWhereNull('content_hash');
                    })),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::withdrawApprovalAction(),
                self::retireAction(),
            ])
            ->emptyStateHeading(__('model_artifact.table.empty_heading'))
            ->emptyStateDescription(__('model_artifact.table.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('model_artifact.sections.identity'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('model_artifact.fields.name'))
                            ->weight('semibold')
                            ->columnSpan(2),

                        TextEntry::make('status')
                            ->label(__('model_artifact.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('format')
                            ->label(__('model_artifact.fields.format'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('format_handling')
                            ->label(__('model_artifact.fields.handling'))
                            ->state(fn (ModelArtifact $record): string => __('model_artifact.handling.'.$record->format->value))
                            ->color(fn (ModelArtifact $record): string => $record->isPickle() ? 'danger' : 'gray')
                            ->columnSpan(2),

                        TextEntry::make('architecture')
                            ->label(__('model_artifact.fields.architecture'))
                            ->placeholder('—'),

                        TextEntry::make('quantization')
                            ->label(__('model_artifact.fields.quantization'))
                            ->placeholder('—'),

                        TextEntry::make('license')
                            ->label(__('model_artifact.fields.license'))
                            ->placeholder(__('model_artifact.not_recorded')),

                        TextEntry::make('context_length')
                            ->label(__('model_artifact.fields.context_length'))
                            ->placeholder('—'),

                        TextEntry::make('tensor_count')
                            ->label(__('model_artifact.fields.tensor_count'))
                            ->placeholder('—'),

                        TextEntry::make('file_count')
                            ->label(__('model_artifact.fields.file_count')),

                        TextEntry::make('total_bytes')
                            ->label(__('model_artifact.fields.total_bytes'))
                            ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                    ]),

                Section::make(__('model_artifact.sections.provenance'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('provenance_status')
                            ->label(__('model_artifact.fields.provenance'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __('model_artifact.provenance.'.$state))
                            ->color(fn (string $state): string => match ($state) {
                                'verified' => 'success',
                                'partial' => 'warning',
                                default => 'danger',
                            }),

                        TextEntry::make('source')
                            ->label(__('model_artifact.fields.source'))
                            ->placeholder(__('model_artifact.not_recorded'))
                            ->copyable()
                            ->columnSpan(2),

                        TextEntry::make('revision')
                            ->label(__('model_artifact.fields.revision'))
                            ->placeholder(__('model_artifact.not_recorded'))
                            ->copyable(),

                        TextEntry::make('content_hash')
                            ->label(__('model_artifact.fields.content_hash'))
                            ->placeholder(__('model_artifact.hash_skipped'))
                            ->copyable()
                            ->columnSpan(2),

                        TextEntry::make('conversion_tool')
                            ->label(__('model_artifact.fields.conversion_tool'))
                            ->placeholder(__('model_artifact.unknown')),

                        TextEntry::make('conversion_version')
                            ->label(__('model_artifact.fields.conversion_version'))
                            ->placeholder('—'),

                        TextEntry::make('conversion_recorded_at')
                            ->label(__('model_artifact.fields.conversion_recorded_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('tokenizer_hash')
                            ->label(__('model_artifact.fields.tokenizer_hash'))
                            ->placeholder(__('model_artifact.not_recorded'))
                            ->copyable(),

                        TextEntry::make('chat_template_hash')
                            ->label(__('model_artifact.fields.chat_template_hash'))
                            ->placeholder(__('model_artifact.not_recorded'))
                            ->copyable(),

                        TextEntry::make('provenance_verified_at')
                            ->label(__('model_artifact.fields.provenance_verified_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make(__('model_artifact.sections.scanning'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('scan_tool')
                            ->label(__('model_artifact.fields.scan_tool'))
                            ->placeholder(__('model_artifact.never_scanned')),

                        TextEntry::make('scan_tool_version')
                            ->label(__('model_artifact.fields.scan_tool_version'))
                            ->placeholder('—'),

                        TextEntry::make('last_scanned_at')
                            ->label(__('model_artifact.fields.last_scanned_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('model_artifact.never_scanned')),
                    ]),

                Section::make(__('model_artifact.sections.ownership'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('owner.name')
                            ->label(__('model_artifact.fields.owner'))
                            ->placeholder(__('model_artifact.unassigned')),

                        TextEntry::make('asset.name')
                            ->label(__('model_artifact.fields.asset'))
                            ->placeholder('—')
                            ->url(fn (ModelArtifact $record): ?string => $record->asset
                                ? AssetResource::getUrl('view', ['record' => $record->asset])
                                : null),

                        TextEntry::make('application.name')
                            ->label(__('model_artifact.fields.application'))
                            ->placeholder('—')
                            ->url(fn (ModelArtifact $record): ?string => $record->application
                                ? ApplicationResource::getUrl('view', ['record' => $record->application])
                                : null),

                        TextEntry::make('notes')
                            ->label(__('model_artifact.fields.notes'))
                            ->placeholder(__('model_artifact.no_notes'))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap']),
                    ]),

                Section::make(__('model_artifact.sections.approval'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('approvedBy.name')
                            ->label(__('model_artifact.fields.approved_by'))
                            ->placeholder(__('model_artifact.not_approved')),

                        TextEntry::make('approved_at')
                            ->label(__('model_artifact.fields.approved_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('model_artifact.not_approved')),

                        TextEntry::make('approval_state')
                            ->label(__('model_artifact.fields.approval_state'))
                            ->state(fn (ModelArtifact $record): string => $record->isApproved()
                                ? __('model_artifact.approval.current')
                                : __('model_artifact.approval.none'))
                            ->badge()
                            ->color(fn (ModelArtifact $record): string => $record->isApproved() ? 'success' : 'gray'),
                    ])
                    ->description(__('model_artifact.sections.approval_help')),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('model_artifact.actions.approve'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('model_artifact.actions.approve_confirm'))
            ->visible(fn (ModelArtifact $record): bool => ! $record->isApproved() && $record->canBeApproved())
            ->authorize(fn (ModelArtifact $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (ModelArtifact $record): void {
                $user = auth()->user();

                if ($user === null || ! $record->canBeApproved()) {
                    Notification::make()
                        ->title(__('model_artifact.notifications.approval_refused'))
                        ->danger()
                        ->send();

                    return;
                }

                $record->update([
                    'status' => ModelArtifactStatus::Approved,
                    'approved_by' => $user->getKey(),
                    'approved_at' => now(),
                ]);

                Notification::make()
                    ->title(__('model_artifact.notifications.approved'))
                    ->success()
                    ->send();
            });
    }

    public static function withdrawApprovalAction(): Action
    {
        return Action::make('withdrawApproval')
            ->label(__('model_artifact.actions.withdraw'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('model_artifact.actions.withdraw_confirm'))
            ->visible(fn (ModelArtifact $record): bool => $record->isApproved())
            ->authorize(fn (ModelArtifact $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (ModelArtifact $record): void {
                $record->update([
                    'status' => ModelArtifactStatus::Proposed,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

                Notification::make()
                    ->title(__('model_artifact.notifications.withdrawn'))
                    ->warning()
                    ->send();
            });
    }

    public static function retireAction(): Action
    {
        return Action::make('retire')
            ->label(__('model_artifact.actions.retire'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('model_artifact.actions.retire_confirm'))
            ->visible(fn (ModelArtifact $record): bool => $record->status !== ModelArtifactStatus::Retired)
            ->authorize(fn (ModelArtifact $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (ModelArtifact $record): void {
                $record->update(['status' => ModelArtifactStatus::Retired]);

                Notification::make()
                    ->title(__('model_artifact.notifications.retired'))
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            'files' => FilesRelationManager::class,
            'findings' => FindingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModelArtifacts::route('/'),
            'view' => ViewModelArtifact::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'source', 'revision'];
    }
}
