<?php

namespace App\Filament\Widgets;

use App\Enums\ControlStatus;
use App\Filament\Resources\FindingResource;
use App\Models\ControlValidation;
use App\Models\Finding;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The first thing an operator should see: open findings, whether they are
 * overdue, and whether automated checks are failing or blind.
 */
class ContinuousVerificationWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $open = Finding::query()->open()->count();
        $overdue = Finding::query()
            ->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $failed = ControlValidation::query()
            ->where('status', ControlStatus::Failed)
            ->where('evaluated_at', '>=', now()->subDay())
            ->count();

        $unknown = ControlValidation::query()
            ->where('status', ControlStatus::Unknown)
            ->where('evaluated_at', '>=', now()->subDay())
            ->count();

        return [
            Stat::make(__('Open findings'), $open)
                ->description($overdue.' '.__('overdue'))
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($open > 0 ? 'danger' : 'success')
                ->url(FindingResource::getUrl()),

            Stat::make(__('Failed checks (24h)'), $failed)
                ->description(__('Latest validation results'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($failed > 0 ? 'danger' : 'success'),

            Stat::make(__('Unknown checks (24h)'), $unknown)
                ->description(__('Missing or incomplete evidence'))
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($unknown > 0 ? 'warning' : 'gray'),
        ];
    }
}
