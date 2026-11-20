<?php

namespace App\Filament\Widgets;

use App\Models\DiscoveryFinding;
use Filament\Widgets\ChartWidget;

/**
 * Open findings by age. A growing old bucket is a programme problem, not a
 * detector problem.
 */
class DspmAgingChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return __('dspm.charts.aging_heading');
    }

    public function getDescription(): ?string
    {
        return __('dspm.charts.aging_description');
    }

    protected function getData(): array
    {
        $base = DiscoveryFinding::query()->whereIn('status', ['open', 'acknowledged']);

        $last7 = (clone $base)
            ->whereRaw('coalesce(remote_created_at, created_at) >= ?', [now()->subDays(7)])
            ->count();
        $days7to30 = (clone $base)
            ->whereRaw('coalesce(remote_created_at, created_at) < ?', [now()->subDays(7)])
            ->whereRaw('coalesce(remote_created_at, created_at) >= ?', [now()->subDays(30)])
            ->count();
        $days31to90 = (clone $base)
            ->whereRaw('coalesce(remote_created_at, created_at) < ?', [now()->subDays(30)])
            ->whereRaw('coalesce(remote_created_at, created_at) >= ?', [now()->subDays(90)])
            ->count();
        $older = (clone $base)
            ->whereRaw('coalesce(remote_created_at, created_at) < ?', [now()->subDays(90)])
            ->count();

        return [
            'labels' => [
                __('dspm.aging.last_7'),
                __('dspm.aging.days_7_30'),
                __('dspm.aging.days_31_90'),
                __('dspm.aging.older'),
            ],
            'datasets' => [[
                'data' => [$last7, $days7to30, $days31to90, $older],
                'backgroundColor' => [
                    'rgb(16, 185, 129)',
                    'rgb(245, 158, 11)',
                    'rgb(249, 115, 22)',
                    'rgb(239, 68, 68)',
                ],
                'borderWidth' => 0,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
