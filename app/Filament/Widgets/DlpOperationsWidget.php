<?php

namespace App\Filament\Widgets;

use App\Models\DlpFinding;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * DLP operations alongside DSPM posture. DSPM says where the data is; DLP
 * says what the organisation did when it moved.
 */
class DlpOperationsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 7;

    protected function getStats(): array
    {
        $since = now()->subDays(7);
        $url = route('filament.admin.resources.dlp-findings.index');

        $blocked = DlpFinding::query()
            ->where('action', 'blocked')
            ->where('created_at', '>=', $since)
            ->count();
        $flagged = DlpFinding::query()
            ->where('action', 'flagged')
            ->where('created_at', '>=', $since)
            ->count();
        $criticalHigh = DlpFinding::query()
            ->whereIn('severity', ['critical', 'high'])
            ->where('created_at', '>=', $since)
            ->count();

        return [
            Stat::make(__('dspm.dlp.blocked'), $blocked)
                ->description($blocked === 0 ? __('dspm.dlp.no_events') : __('dspm.dlp.blocked_description'))
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color($blocked === 0 ? 'gray' : 'success')
                ->url($url),

            Stat::make(__('dspm.dlp.flagged'), $flagged)
                ->description($flagged === 0 ? __('dspm.dlp.no_events') : __('dspm.dlp.flagged_description'))
                ->descriptionIcon('heroicon-m-flag')
                ->color($flagged === 0 ? 'gray' : 'warning')
                ->url($url),

            Stat::make(__('dspm.dlp.critical_high'), $criticalHigh)
                ->description($criticalHigh === 0 ? __('dspm.dlp.no_events') : __('dspm.dlp.critical_high_description'))
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($criticalHigh === 0 ? 'gray' : 'danger')
                ->url($url),
        ];
    }
}
