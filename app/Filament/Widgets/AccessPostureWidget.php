<?php

namespace App\Filament\Widgets;

use App\Enums\AccessGrantState;
use App\Enums\AccessReason;
use App\Models\AccessDecision;
use App\Models\AccessGrant;
use App\Models\ProtectedResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coverage and freshness first, counts second (ADR 0049).
 *
 * Every figure here is an aggregate over records the reader can also open. The
 * links go to the registers, not to a hidden drill-down.
 */
class AccessPostureWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $resources = ProtectedResource::query()->count();
        $stale = ProtectedResource::query()->stale()->count();
        $unowned = ProtectedResource::query()->whereNull('owner_id')->count();

        $activeGrants = AccessGrant::query()
            ->where('state', AccessGrantState::Active->value)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $breakGlass = AccessDecision::query()
            ->where('reason', AccessReason::BreakGlass->value)
            ->where('decided_at', '>=', now()->subDays(7))
            ->count();

        $overdueReviews = AccessGrant::query()->reviewOverdue()->count();

        return [
            Stat::make(__('access_governance.stats.resources'), $resources)
                ->description(__('access_governance.stats.stale', ['count' => $stale]))
                ->descriptionIcon($stale === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($stale === 0 ? 'success' : 'warning')
                ->url(route('filament.app.resources.protected-resources.index')),

            Stat::make(__('access_governance.stats.active_grants'), $activeGrants)
                ->description($unowned === 0
                    ? __('access_governance.stats.grants_description')
                    : __('access_governance.stats.unowned', ['count' => $unowned]))
                ->descriptionIcon('heroicon-m-key')
                ->color($activeGrants === 0 ? 'gray' : 'success')
                ->url(route('filament.app.resources.access-grants.index')),

            Stat::make(__('access_governance.stats.break_glass'), $breakGlass)
                ->descriptionIcon('heroicon-m-lock-open')
                ->color($breakGlass === 0 ? 'gray' : 'warning')
                ->url(route('filament.app.resources.access-decisions.index')),

            Stat::make(__('access_governance.stats.overdue_reviews'), $overdueReviews)
                ->descriptionIcon($overdueReviews === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                ->color($overdueReviews === 0 ? 'success' : 'danger')
                ->url(route('filament.app.resources.access-grants.index')),
        ];
    }
}
