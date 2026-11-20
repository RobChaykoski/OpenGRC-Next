<?php

namespace App\Filament\Pages;

use App\Enums\RiskLevel;
use App\Models\Risk;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Aggregate view of active risks by likelihood and impact.
 *
 * The page counts; it does not decide appetite. A cell links to the filtered risk
 * list so the reader can inspect the records behind the number.
 */
class RiskHeatMap extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'risk-heat-map';

    protected string $view = 'filament.pages.risk-heat-map';

    public static function getNavigationLabel(): string
    {
        return 'Risk heat map';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Entities';
    }

    public function getTitle(): string
    {
        return 'Risk heat map';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $active = Risk::query()
            ->where('is_active', true)
            ->get(['inherent_likelihood', 'inherent_impact', 'residual_likelihood', 'residual_impact']);

        return [
            'levels' => [5, 4, 3, 2, 1],
            'inherent' => $this->matrix($active, 'inherent_likelihood', 'inherent_impact'),
            'residual' => $this->matrix($active, 'residual_likelihood', 'residual_impact'),
            'total' => $active->count(),
            'veryHigh' => $active->filter(
                fn (Risk $risk): bool => ((int) $risk->getAttribute('inherent_likelihood'))
                    * ((int) $risk->getAttribute('inherent_impact')) >= 18
            )->count(),
            'colors' => $this->colors(),
        ];
    }

    /**
     * @param  Collection<int, Risk>  $risks
     * @return array<string, int>
     */
    protected function matrix($risks, string $likelihood, string $impact): array
    {
        $matrix = [];

        foreach ($risks as $risk) {
            $row = (int) $risk->getAttribute($likelihood);
            $column = (int) $risk->getAttribute($impact);

            if ($row < 1 || $column < 1) {
                continue;
            }

            $key = $row.'-'.$column;
            $matrix[$key] = ($matrix[$key] ?? 0) + 1;
        }

        return $matrix;
    }

    /**
     * @return array<int, string>
     */
    protected function colors(): array
    {
        $colors = [];

        for ($likelihood = 1; $likelihood <= 5; $likelihood++) {
            for ($impact = 1; $impact <= 5; $impact++) {
                $colors[$likelihood * $impact] = $this->colorForScore($likelihood * $impact);
            }
        }

        return $colors;
    }

    protected function colorForScore(int $score): string
    {
        return match (RiskLevel::fromScore($score)) {
            RiskLevel::VeryHigh => '#fecaca',
            RiskLevel::High => '#fed7aa',
            RiskLevel::Moderate => '#fef08a',
            RiskLevel::Low => '#bfdbfe',
            RiskLevel::VeryLow => '#bbf7d0',
        };
    }
}
