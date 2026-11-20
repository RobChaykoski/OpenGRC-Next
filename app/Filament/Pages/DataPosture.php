<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * The DLP/DSPM teaching dashboard.
 *
 * It is deliberately aggregate-only: coverage, classification, exposure, aging
 * and policy effectiveness. File paths and match previews stay on the finding
 * detail page, behind the same read-only access rule as the rest of the console.
 */
class DataPosture extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'data-posture';

    protected string $view = 'filament.pages.data-posture';

    public static function getNavigationLabel(): string
    {
        return __('dspm.navigation');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('discovery.navigation.group');
    }

    public function getTitle(): string
    {
        return __('dspm.title');
    }
}
