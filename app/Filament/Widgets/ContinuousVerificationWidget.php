<?php

namespace App\Filament\Widgets;

use App\Enums\ControlStatus;
use App\Filament\Resources\FindingResource;
use App\Models\ControlValidation;
use App\Models\Finding;
use App\Models\IntegrityCheck;
use App\Services\Integrity\IntegrityVerifier;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The first thing an operator should see: open findings, whether they are
 * overdue, whether automated checks are failing or blind, and whether the
 * append-only chains still verify.
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

            $this->integrityStat(),
        ];
    }

    /**
     * A chain nobody checked is not a chain that passed. The newest run per
     * chain decides the colour; a stopped schedule is shown as attention rather
     * than staying green forever.
     */
    protected function integrityStat(): Stat
    {
        $runs = collect(IntegrityVerifier::CHECKS)
            ->mapWithKeys(fn (string $check): array => [$check => IntegrityCheck::latestFor($check)]);

        $staleAfter = now()->subHours((int) config('integrity.stale_after_hours', 26));
        $staleWindow = (int) config('integrity.stale_after_hours', 26);

        $latest = $runs->filter()->sortByDesc(fn (IntegrityCheck $run) => $run->checked_at)->first();
        $oldest = $runs->filter()->sortBy(fn (IntegrityCheck $run) => $run->checked_at)->first();

        $failed = $runs->first(fn (?IntegrityCheck $run): bool => (bool) $run?->failed());
        $warned = $runs->first(fn (?IntegrityCheck $run): bool => (bool) $run?->warned());
        $missing = $runs->contains(fn (?IntegrityCheck $run): bool => $run === null);
        $stale = $staleWindow > 0 && ($oldest?->checked_at?->lt($staleAfter) ?? false);

        if ($failed instanceof IntegrityCheck) {
            return $this->integrityResult(
                __('integrity.widget.value.fail'),
                __('integrity.widget.description.issue', ['summary' => $failed->summary]),
                'danger',
                'heroicon-m-shield-exclamation'
            );
        }

        if ($missing || $latest === null) {
            return $this->integrityResult(
                __('integrity.widget.value.never'),
                __('integrity.widget.description.never'),
                'gray',
                'heroicon-m-question-mark-circle'
            );
        }

        if ($stale) {
            return $this->integrityResult(
                __('integrity.widget.value.warn'),
                __('integrity.widget.description.stale', ['time' => $oldest->checked_at->diffForHumans()]),
                'warning',
                'heroicon-m-exclamation-triangle'
            );
        }

        if ($warned instanceof IntegrityCheck) {
            return $this->integrityResult(
                __('integrity.widget.value.warn'),
                __('integrity.widget.description.issue', ['summary' => $warned->summary]),
                'warning',
                'heroicon-m-exclamation-triangle'
            );
        }

        return $this->integrityResult(
            __('integrity.widget.value.pass'),
            __('integrity.widget.description.last_run', ['time' => $latest->checked_at->diffForHumans()]),
            'success',
            'heroicon-m-shield-check'
        );
    }

    protected function integrityResult(string $value, string $description, string $color, string $icon): Stat
    {
        return Stat::make(__('integrity.widget.title'), $value)
            ->description($description)
            ->descriptionIcon($icon)
            ->color($color);
    }
}
