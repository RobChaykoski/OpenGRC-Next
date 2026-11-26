<?php

namespace App\Filament\Widgets;

use App\Models\DiscoveryFinding;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * The top exposed assets. Asset names only: paths and previews stay on the
 * finding detail page, behind the read-only console policy.
 */
class DspmExposedAssetsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DiscoveryFinding::query()
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->selectRaw(
                        'min(id) as id, asset_name, count(*) as open_count, '
                        .'sum(case when severity in (?, ?) then 1 else 0 end) as high_count, '
                        .'min(remote_created_at) as oldest_at, max(synced_at) as last_synced_at',
                        ['critical', 'high']
                    )
                    ->groupBy('asset_name')
                    ->orderByDesc('open_count')
                    ->limit(8)
            )
            ->heading(__('dspm.exposure.heading'))
            ->description(__('dspm.exposure.description'))
            // Aggregated rows have no real primary key; skip the implicit
            // ORDER BY discovery_findings.id that ONLY_FULL_GROUP_BY rejects.
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('asset_name')
                    ->label(__('dspm.exposure.asset'))
                    ->placeholder(__('dspm.exposure.unmapped'))
                    ->searchable(),

                TextColumn::make('open_count')
                    ->label(__('dspm.exposure.open'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('high_count')
                    ->label(__('dspm.exposure.critical_high'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('oldest_at')
                    ->label(__('dspm.exposure.oldest'))
                    ->dateTime('M j, Y')
                    ->placeholder('—'),

                TextColumn::make('last_synced_at')
                    ->label(__('dspm.exposure.last_synced'))
                    ->since()
                    ->placeholder('—'),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('dspm.exposure.empty_heading'))
            ->emptyStateDescription(__('dspm.exposure.empty_description'));
    }
}
