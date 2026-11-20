<?php

namespace App\Filament\Admin\Pages\Settings;

use App\Enums\RiskLevel;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Programme-level risk display settings.
 *
 * The appetite threshold is a visual aid on the heat map. It does not enforce a
 * treatment decision and it is not a per-category appetite statement.
 */
class RiskSettings extends BaseSettings
{
    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('Manage Preferences');
    }

    public static function getNavigationGroup(): string
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return 'Risk settings';
    }

    public function form(Schema $schema): Schema
    {
        $options = [];

        for ($score = 1; $score <= 25; $score++) {
            $options[(string) $score] = $score.' — '.RiskLevel::fromScore($score)->getLabel();
        }

        return $schema
            ->components([
                Section::make('Risk appetite')
                    ->description('A visual threshold for the heat map. It does not replace a treatment or acceptance decision.')
                    ->schema([
                        Select::make('risk.appetite_threshold')
                            ->label('Appetite threshold')
                            ->helperText('Scores at or above this value are outlined on the risk heat map. Per-category appetite is still recorded on the risk.')
                            ->options($options)
                            ->default('13')
                            ->required(),
                    ]),
            ]);
    }
}
