<?php

namespace App\Filament\Widgets;

use App\Models\DiscoveryFinding;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Open findings by data category. Categories are the business vocabulary:
 * personal information, health, cardholder data, secrets, credentials.
 */
class DspmCategoryChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return __('dspm.charts.category_heading');
    }

    public function getDescription(): ?string
    {
        return __('dspm.charts.category_description');
    }

    protected function getData(): array
    {
        $rows = DiscoveryFinding::query()
            ->whereIn('status', ['open', 'acknowledged'])
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $category = $row->category;
            $key = is_string($category) ? strtolower($category) : 'uncategorized';
            $labels[] = Lang::has('dspm.category.'.$key)
                ? __('dspm.category.'.$key)
                : Str::headline($key);
            $data[] = (int) $row->getAttribute('total');
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => [
                    'rgb(19, 117, 160)',
                    'rgb(245, 158, 11)',
                    'rgb(239, 68, 68)',
                    'rgb(16, 185, 129)',
                    'rgb(99, 102, 241)',
                    'rgb(100, 116, 139)',
                    'rgb(236, 72, 153)',
                    'rgb(20, 184, 166)',
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
