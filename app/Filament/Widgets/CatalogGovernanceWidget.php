<?php

namespace App\Filament\Widgets;

use App\Models\EvidenceRecord;
use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * Catalog ownership and classification coverage from OpenMetadata evidence.
 *
 * The widget reads the latest aggregate per catalog service. It never renders a
 * table name, column, sample or row; the payload only carries counts.
 */
class CatalogGovernanceWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 8;

    protected function getStats(): array
    {
        $records = $this->latestRecords();

        if ($records->isEmpty()) {
            return [
                Stat::make(__('dspm.catalog.heading'), '—')
                    ->description(__('dspm.catalog.not_configured'))
                    ->descriptionIcon('heroicon-m-circle-stack')
                    ->color('gray'),
            ];
        }

        $tables = 0;
        $owned = 0;
        $classified = 0;
        $lastCollected = null;

        foreach ($records as $record) {
            $payload = is_array($record->payload) ? $record->payload : [];

            $tables += (int) ($payload['tableCount'] ?? 0);
            $owned += (int) ($payload['ownedTableCount'] ?? 0);
            $classified += (int) ($payload['classifiedTableCount'] ?? 0);

            $collectedAt = $record->collected_at;

            if ($collectedAt instanceof CarbonInterface
                && ($lastCollected === null || $collectedAt->gt($lastCollected))) {
                $lastCollected = $collectedAt;
            }
        }

        $ownerPercent = $tables > 0 ? round($owned / $tables * 100, 1) : null;
        $classifiedPercent = $tables > 0 ? round($classified / $tables * 100, 1) : null;
        $staleAfter = max(1, (int) config('openmetadata.stale_after_days', 7));

        $coverageColor = fn (?float $value): string => match (true) {
            $value === null => 'gray',
            $value >= 90 => 'success',
            $value >= 50 => 'warning',
            default => 'danger',
        };

        $freshnessColor = match (true) {
            $lastCollected === null => 'gray',
            $lastCollected->gte(now()->subDays($staleAfter)) => 'success',
            $lastCollected->gte(now()->subDays($staleAfter * 2)) => 'warning',
            default => 'danger',
        };

        return [
            Stat::make(__('dspm.catalog.services'), $records->count())
                ->description(__('dspm.catalog.services_detail', ['tables' => number_format($tables)]))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('gray'),

            Stat::make(
                __('dspm.catalog.owners'),
                $ownerPercent === null ? '—' : $ownerPercent.'%'
            )
                ->description(__('dspm.catalog.owners_detail', [
                    'owned' => number_format($owned),
                    'total' => number_format($tables),
                ]))
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($coverageColor($ownerPercent)),

            Stat::make(
                __('dspm.catalog.classification'),
                $classifiedPercent === null ? '—' : $classifiedPercent.'%'
            )
                ->description(__('dspm.catalog.classification_detail', [
                    'classified' => number_format($classified),
                    'total' => number_format($tables),
                ]))
                ->descriptionIcon('heroicon-m-tag')
                ->color($coverageColor($classifiedPercent)),

            Stat::make(
                __('dspm.catalog.freshness'),
                $lastCollected?->diffForHumans() ?? __('dspm.catalog.never')
            )
                ->description($lastCollected === null
                    ? __('dspm.catalog.not_configured')
                    : __('dspm.catalog.last_collected', ['date' => $lastCollected->toDayDateTimeString()]))
                ->descriptionIcon('heroicon-m-clock')
                ->color($freshnessColor),
        ];
    }

    /**
     * The latest aggregate record per catalog service.
     *
     * @return Collection<int, EvidenceRecord>
     */
    protected function latestRecords(): Collection
    {
        $ids = EvidenceRecord::query()
            ->selectRaw('max(id)')
            ->where('evidence_type', 'catalog-governance')
            ->groupBy('external_id');

        return EvidenceRecord::query()->whereIn('id', $ids)->get();
    }
}
