<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscoveryFindingResource\Pages\ListDiscoveryFindings;
use App\Filament\Resources\DiscoveryFindingResource\Pages\ViewDiscoveryFinding;
use App\Models\DiscoveryFinding;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Read-only review queue for discovery findings. A finding keeps the path, a
 * short preview and a hash of the match; the matched content itself is never
 * stored or rendered.
 */
class DiscoveryFindingResource extends Resource
{
    protected static ?string $model = DiscoveryFinding::class;

    protected static ?string $recordTitleAttribute = 'asset_name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?int $navigationSort = 48;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('discovery.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('discovery.finding.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('discovery.finding.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discovery.finding.plural');
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
                TextColumn::make('severity')
                    ->label(__('discovery.finding.columns.severity'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): ?string => self::severityLabel($state))
                    ->color(fn (mixed $state): string => self::severityColor($state))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('discovery.finding.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): ?string => self::statusLabel($state))
                    ->color(fn (mixed $state): string => self::statusColor($state))
                    ->sortable(),

                TextColumn::make('asset_name')
                    ->label(__('discovery.finding.columns.asset'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('detector_key')
                    ->label(__('discovery.finding.columns.detector'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label(__('discovery.finding.columns.category'))
                    ->placeholder(__('discovery.placeholders.not_recorded'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('classification')
                    ->label(__('discovery.finding.columns.classification'))
                    ->placeholder(__('discovery.placeholders.not_recorded'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('path')
                    ->label(__('discovery.finding.columns.path'))
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('confidence')
                    ->label(__('discovery.finding.columns.confidence'))
                    ->sortable(),

                TextColumn::make('synced_at')
                    ->label(__('discovery.finding.columns.synced_at'))
                    ->dateTime('M j, Y H:i')
                    ->placeholder(__('discovery.placeholders.not_recorded'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('discovery.filters.status'))
                    ->options(self::statusOptions()),

                SelectFilter::make('severity')
                    ->label(__('discovery.filters.severity'))
                    ->options(self::severityOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('synced_at', 'desc')
            ->emptyStateHeading(__('discovery.finding.empty_heading'))
            ->emptyStateDescription(__('discovery.finding.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('discovery.finding.sections.finding'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('severity')
                            ->label(__('discovery.finding.columns.severity'))
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): ?string => self::severityLabel($state))
                            ->color(fn (mixed $state): string => self::severityColor($state)),

                        TextEntry::make('status')
                            ->label(__('discovery.finding.columns.status'))
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): ?string => self::statusLabel($state))
                            ->color(fn (mixed $state): string => self::statusColor($state)),

                        TextEntry::make('confidence')
                            ->label(__('discovery.finding.columns.confidence')),

                        TextEntry::make('detector_key')
                            ->label(__('discovery.finding.columns.detector')),

                        TextEntry::make('category')
                            ->label(__('discovery.finding.columns.category'))
                            ->placeholder(__('discovery.placeholders.not_recorded')),

                        TextEntry::make('classification')
                            ->label(__('discovery.finding.columns.classification'))
                            ->placeholder(__('discovery.placeholders.not_recorded')),
                    ]),

                Section::make(__('discovery.finding.sections.source'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('remote_id')
                            ->label(__('discovery.finding.entries.remote_id'))
                            ->copyable(),

                        TextEntry::make('remote_scan_id')
                            ->label(__('discovery.finding.entries.remote_scan_id'))
                            ->placeholder(__('discovery.placeholders.not_recorded'))
                            ->copyable(),

                        TextEntry::make('discovery_scan_id')
                            ->label(__('discovery.finding.entries.scan'))
                            ->placeholder(__('discovery.placeholders.not_recorded'))
                            ->copyable(),

                        TextEntry::make('asset_id')
                            ->label(__('discovery.finding.entries.asset_id'))
                            ->placeholder(__('discovery.placeholders.not_recorded'))
                            ->copyable(),

                        TextEntry::make('asset_name')
                            ->label(__('discovery.finding.columns.asset')),
                    ]),

                Section::make(__('discovery.finding.sections.match'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('path')
                            ->label(__('discovery.finding.columns.path'))
                            ->placeholder(__('discovery.placeholders.not_recorded'))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap break-words']),

                        TextEntry::make('match_preview')
                            ->label(__('discovery.finding.entries.match_preview'))
                            ->placeholder(__('discovery.placeholders.none'))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap break-words font-mono text-xs']),

                        TextEntry::make('match_sha256')
                            ->label(__('discovery.finding.entries.match_sha256'))
                            ->placeholder(__('discovery.placeholders.not_recorded'))
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('discovery.finding.sections.timing'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('remote_created_at')
                            ->label(__('discovery.finding.entries.remote_created_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('discovery.placeholders.not_recorded')),

                        TextEntry::make('synced_at')
                            ->label(__('discovery.finding.entries.synced_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('discovery.placeholders.not_recorded')),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscoveryFindings::route('/'),
            'view' => ViewDiscoveryFinding::route('/{record}'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'open' => __('discovery.status.open'),
            'acknowledged' => __('discovery.status.acknowledged'),
            'resolved' => __('discovery.status.resolved'),
            'suppressed' => __('discovery.status.suppressed'),
            'false_positive' => __('discovery.status.false_positive'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function severityOptions(): array
    {
        return [
            'critical' => __('discovery.severity.critical'),
            'high' => __('discovery.severity.high'),
            'medium' => __('discovery.severity.medium'),
            'low' => __('discovery.severity.low'),
            'info' => __('discovery.severity.info'),
        ];
    }

    public static function statusLabel(mixed $state): ?string
    {
        return self::label('discovery.status', $state);
    }

    public static function severityLabel(mixed $state): ?string
    {
        return self::label('discovery.severity', $state);
    }

    public static function statusColor(mixed $state): string
    {
        return match (strtolower((string) self::stateValue($state))) {
            'resolved' => 'success',
            'acknowledged' => 'info',
            'open', 'pending' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function severityColor(mixed $state): string
    {
        return match (strtolower((string) self::stateValue($state))) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low', 'info', 'informational' => 'gray',
            default => 'gray',
        };
    }

    protected static function label(string $prefix, mixed $state): ?string
    {
        $value = self::stateValue($state);

        if ($value === null) {
            return null;
        }

        $key = $prefix.'.'.strtolower($value);

        return Lang::has($key) ? __($key) : Str::headline($value);
    }

    protected static function stateValue(mixed $state): ?string
    {
        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        return is_string($state) && $state !== '' ? $state : null;
    }
}
