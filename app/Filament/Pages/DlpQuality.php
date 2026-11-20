<?php

namespace App\Filament\Pages;

use App\Services\Dlp\DlpQualityMetrics;
use Filament\Pages\Page;

/**
 * False-positive quality for the DLP identifier types.
 *
 * Aggregate only. It counts reviewed findings; it never shows content, matched
 * values or a person's file. The targets are programme targets with a recorded
 * reference, not a universal industry benchmark.
 */
class DlpQuality extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Discovery';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'dlp-quality';

    protected string $view = 'filament.pages.dlp-quality';

    public static function getNavigationLabel(): string
    {
        return 'DLP quality';
    }

    public function getTitle(): string
    {
        return 'DLP false-positive quality';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'summary' => app(DlpQualityMetrics::class)->summary(90),
        ];
    }
}
