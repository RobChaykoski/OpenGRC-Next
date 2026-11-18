<?php

namespace App\Filament\Widgets;

use App\Enums\PolicyDocumentationStatus;
use App\Enums\PolicyEffectivenessStatus;
use App\Enums\PolicyImplementationStatus;
use App\Models\PolicyReadinessAssessment;
use App\Models\PolicyReadinessItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * Maturity is the average completion of each status sequence over the
 * applicable items in the latest assessment. The three dimensions are
 * intentionally separate: approved documentation is not implementation.
 */
class PolicyReadinessStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $assessment = PolicyReadinessAssessment::query()
            ->latest('imported_at')
            ->first();

        if ($assessment === null) {
            return [
                Stat::make(__('policy_readiness.stats.no_assessment'), '—')
                    ->description(__('policy_readiness.table.empty_description')),
            ];
        }

        $items = $assessment->items()->get();
        $applicable = $items->filter(fn ($item): bool => $item->isApplicable());
        $count = $applicable->count();

        $documentation = $this->maturity($applicable, fn ($item): float => $item->documentation_status?->score() ?? 0.0);
        $implementation = $this->maturity($applicable, fn ($item): float => $item->implementation_status?->score() ?? 0.0);
        $effectiveness = $this->maturity($applicable, fn ($item): float => $item->effectiveness_status?->score() ?? 0.0);

        $needsAttention = $applicable->filter(
            fn ($item): bool => $item->documentation_status !== PolicyDocumentationStatus::Approved
                || $item->implementation_status !== PolicyImplementationStatus::Implemented
                || $item->effectiveness_status !== PolicyEffectivenessStatus::OperatingEffective
        )->count();

        return [
            Stat::make(__('policy_readiness.stats.applicable'), $count)
                ->description($assessment->name)
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make(__('policy_readiness.stats.documentation'), $documentation.'%')
                ->description(__('policy_readiness.table.documentation'))
                ->color($this->color($documentation)),

            Stat::make(__('policy_readiness.stats.implementation'), $implementation.'%')
                ->description(__('policy_readiness.table.implementation'))
                ->color($this->color($implementation)),

            Stat::make(__('policy_readiness.stats.effectiveness'), $effectiveness.'%')
                ->description(__('policy_readiness.table.effectiveness'))
                ->color($this->color($effectiveness)),

            Stat::make(__('policy_readiness.stats.needs_attention'), $needsAttention)
                ->description($count > 0 ? round(($needsAttention / $count) * 100).'% of applicable' : '—')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($needsAttention > 0 ? 'warning' : 'success'),
        ];
    }

    /**
     * @param  Collection<int, PolicyReadinessItem>  $items
     */
    protected function maturity($items, callable $score): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        return (int) round(($items->avg($score) ?? 0) * 100);
    }

    protected function color(int $maturity): string
    {
        return match (true) {
            $maturity >= 80 => 'success',
            $maturity >= 40 => 'warning',
            default => 'danger',
        };
    }
}
