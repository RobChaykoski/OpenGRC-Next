<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DiscoveryFindingResource;
use App\Filament\Resources\DiscoveryScanResource;
use App\Models\Asset;
use App\Models\DiscoveryFinding;
use App\Models\DiscoveryScan;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * DSPM posture at a glance: coverage, exposure, ownership and freshness.
 *
 * The widget is aggregate-only. It never renders a path, a preview or a hash.
 */
class DspmPostureWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $openStatuses = ['open', 'acknowledged'];

        $scans = DiscoveryScan::query()->count();
        $lastSync = DiscoveryScan::query()->max('synced_at');
        $lastSyncAt = is_string($lastSync) ? CarbonImmutable::parse($lastSync) : null;

        $open = DiscoveryFinding::query()->whereIn('status', $openStatuses)->count();
        $criticalHigh = DiscoveryFinding::query()
            ->whereIn('status', $openStatuses)
            ->whereIn('severity', ['critical', 'high'])
            ->count();
        $unmapped = DiscoveryFinding::query()
            ->whereIn('status', $openStatuses)
            ->whereNull('asset_name')
            ->count();

        $totalAssets = Asset::query()->count();
        $scannedAssets = DiscoveryScan::query()
            ->whereNotNull('asset_id')
            ->distinct()
            ->count('asset_id');
        $coverage = $totalAssets > 0
            ? min(100, (int) round($scannedAssets / $totalAssets * 100))
            : null;

        $openColor = match (true) {
            $scans === 0 => 'gray',
            $criticalHigh > 0 => 'danger',
            $open > 0 => 'warning',
            default => 'success',
        };

        $coverageColor = match (true) {
            $coverage === null => 'gray',
            $coverage >= 80 => 'success',
            $coverage >= 50 => 'warning',
            default => 'danger',
        };

        $freshnessColor = match (true) {
            $lastSyncAt === null => 'gray',
            $lastSyncAt->gte(now()->subDay()) => 'success',
            $lastSyncAt->gte(now()->subDays(3)) => 'warning',
            default => 'danger',
        };

        return [
            Stat::make(__('dspm.metrics.open_findings'), $scans === 0 ? '—' : $open)
                ->description(__('dspm.metrics.critical_high', ['count' => $criticalHigh]))
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($openColor)
                ->url(DiscoveryFindingResource::getUrl()),

            Stat::make(
                __('dspm.metrics.coverage'),
                $coverage === null ? '—' : $coverage.'%'
            )
                ->description($coverage === null
                    ? __('dspm.metrics.coverage_unknown')
                    : __('dspm.metrics.coverage_detail', ['scanned' => $scannedAssets, 'total' => $totalAssets]))
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($coverageColor)
                ->url(DiscoveryScanResource::getUrl()),

            Stat::make(
                __('dspm.metrics.freshness'),
                $lastSyncAt?->diffForHumans() ?? __('dspm.metrics.never')
            )
                ->description($lastSyncAt === null
                    ? __('dspm.metrics.run_sync')
                    : __('dspm.metrics.last_sync', ['date' => $lastSyncAt->toDayDateTimeString()]))
                ->descriptionIcon('heroicon-m-clock')
                ->color($freshnessColor)
                ->url(DiscoveryScanResource::getUrl()),

            Stat::make(__('dspm.metrics.unmapped'), $unmapped)
                ->description(__('dspm.metrics.unmapped_detail'))
                ->descriptionIcon('heroicon-m-link-slash')
                ->color($unmapped > 0 ? 'warning' : 'gray')
                ->url(DiscoveryFindingResource::getUrl()),
        ];
    }
}
