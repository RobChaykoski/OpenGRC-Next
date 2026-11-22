<?php

namespace App\Filament\Widgets;

use App\Models\AccessDecision;
use App\Services\Access\ExposureService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * The exposure budget per subject in the configured window.
 *
 * Grouped and aggregated: this is a monitoring view, not a second decision
 * register. The per-decision detail stays on the decision page.
 */
class AccessExposureWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $since = now()->subMinutes((int) config('access_governance.exposure.window_minutes', 60));

        return $table
            ->query(
                AccessDecision::query()
                    ->where('decided_at', '>=', $since)
                    ->selectRaw('min(id) as id, subject_label, count(*) as decision_count, sum(risk_weight) as weight')
                    ->groupBy('subject_label')
                    ->orderByDesc('weight')
                    ->limit(8)
            )
            ->heading(__('access_governance.exposure.heading'))
            ->description(__('access_governance.exposure.description'))
            ->columns([
                TextColumn::make('subject_label')
                    ->label(__('access_governance.exposure.subject'))
                    ->wrap()
                    ->limit(50),

                TextColumn::make('decision_count')
                    ->label(__('access_governance.exposure.decisions'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('weight')
                    ->label(__('access_governance.exposure.weight'))
                    ->numeric()
                    ->sortable()
                    ->color(function ($state): string {
                        $thresholds = app(ExposureService::class)->thresholds();

                        return match (true) {
                            (int) $state >= $thresholds['block'] => 'danger',
                            (int) $state >= $thresholds['step_up'] => 'warning',
                            default => 'gray',
                        };
                    }),
            ])
            ->emptyStateHeading(__('access_governance.exposure.empty'));
    }
}
