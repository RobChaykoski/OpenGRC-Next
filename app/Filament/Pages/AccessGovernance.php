<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class AccessGovernance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static string|\UnitEnum|null $navigationGroup = 'Access governance';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'access-governance';

    protected string $view = 'filament.pages.access-governance';

    public static function getNavigationLabel(): string
    {
        return __('access_governance.navigation.label');
    }

    public function getTitle(): string
    {
        return __('access_governance.title');
    }
}
