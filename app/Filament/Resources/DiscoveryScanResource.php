<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscoveryScanResource\Pages\ListDiscoveryScans;
use App\Filament\Resources\DiscoveryScanResource\Pages\ViewDiscoveryScan;
use App\Models\DiscoveryScan;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Read-only console for discovery scans pulled from OpenDiscoveryEngine. A scan
 * carries counters and timing only; matched content stays on the remote side.
 */
class DiscoveryScanResource extends Resource
{
    protected static ?string $model = DiscoveryScan::class;

    protected static ?string $recordTitleAttribute = 'asset_name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?int $navigationSort = 47;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('discovery.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('discovery.scan.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('discovery.scan.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discovery.scan.plural');
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
            ->columns([
                TextColumn::make('status')
                    ->label(__('discovery.scan.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): ?string => self::statusLabel($state))
                    ->color(fn (mixed $state): string => self::statusColor($state))
                    ->sortable(),

                TextColumn::make('asset_name')
                    ->label(__('discovery.scan.columns.asset'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('profile_name')
                    ->label(__('discovery.scan.columns.profile'))
                    ->placeholder(__('discovery.placeholders.not_recorded'))
                    ->searchable(),

                TextColumn::make('files_seen')
                    ->label(__('discovery.scan.columns.files_seen'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('files_scanned')
                    ->label(__('discovery.scan.columns.files_scanned'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('findings_count')
                    ->label(__('discovery.scan.columns.findings'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('synced_at')
                    ->label(__('discovery.scan.columns.synced_at'))
                    ->dateTime('M j, Y H:i')
                    ->placeholder(__('discovery.placeholders.not_recorded'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('discovery.filters.status'))
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('synced_at', 'desc')
            ->emptyStateHeading(__('discovery.scan.empty_heading'))
            ->emptyStateDescription(__('discovery.scan.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('discovery.scan.sections.scan'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('asset_name')
                            ->label(__('discovery.scan.columns.asset')),

                        TextEntry::make('profile_name')
                            ->label(__('discovery.scan.columns.profile'))
                            ->placeholder(__('discovery.placeholders.not_recorded')),

                        TextEntry::make('status')
                            ->label(__('discovery.scan.columns.status'))
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): ?string => self::statusLabel($state))
                            ->color(fn (mixed $state): string => self::statusColor($state)),

                        TextEntry::make('files_seen')
                            ->label(__('discovery.scan.columns.files_seen')),

                        TextEntry::make('files_scanned')
                            ->label(__('discovery.scan.columns.files_scanned')),

                        TextEntry::make('findings_count')
                            ->label(__('discovery.scan.columns.findings')),

                        TextEntry::make('error')
                            ->label(__('discovery.scan.entries.error'))
                            ->placeholder(__('discovery.placeholders.none'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('discovery.scan.sections.source'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('remote_id')
                            ->label(__('discovery.scan.entries.remote_id'))
                            ->copyable(),

                        TextEntry::make('asset_id')
                            ->label(__('discovery.scan.entries.asset_id'))
                            ->placeholder(__('discovery.placeholders.not_recorded'))
                            ->copyable(),

                        TextEntry::make('profile_id')
                            ->label(__('discovery.scan.entries.profile_id'))
                            ->placeholder(__('discovery.placeholders.not_recorded'))
                            ->copyable(),
                    ]),

                Section::make(__('discovery.scan.sections.timing'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('started_at')
                            ->label(__('discovery.scan.entries.started_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('discovery.placeholders.not_recorded')),

                        TextEntry::make('finished_at')
                            ->label(__('discovery.scan.entries.finished_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('discovery.placeholders.not_recorded')),

                        TextEntry::make('remote_created_at')
                            ->label(__('discovery.scan.entries.remote_created_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('discovery.placeholders.not_recorded')),

                        TextEntry::make('synced_at')
                            ->label(__('discovery.scan.entries.synced_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('discovery.placeholders.not_recorded')),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscoveryScans::route('/'),
            'view' => ViewDiscoveryScan::route('/{record}'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'queued' => __('discovery.status.queued'),
            'running' => __('discovery.status.running'),
            'completed' => __('discovery.status.completed'),
            'partial' => __('discovery.status.partial'),
            'failed' => __('discovery.status.failed'),
            'cancelled' => __('discovery.status.cancelled'),
        ];
    }

    public static function statusLabel(mixed $state): ?string
    {
        $value = self::stateValue($state);

        if ($value === null) {
            return null;
        }

        $key = 'discovery.status.'.strtolower($value);

        return Lang::has($key) ? __($key) : Str::headline($value);
    }

    public static function statusColor(mixed $state): string
    {
        return match (strtolower((string) self::stateValue($state))) {
            'completed', 'complete' => 'success',
            'running' => 'info',
            'queued', 'pending', 'partial' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    protected static function stateValue(mixed $state): ?string
    {
        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        return is_string($state) && $state !== '' ? $state : null;
    }
}
