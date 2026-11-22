<?php

namespace App\Services\Access;

use App\Enums\AccessAction;
use App\Enums\DocumentClassification;
use App\Models\AccessDecision;
use Carbon\CarbonInterface;

/**
 * The weight of sensitive data consumed by a subject in a rolling window.
 *
 * Exposure is derived from decisions, never stored separately, so it cannot
 * drift from the append-only log. Weights are relative, not currency: the
 * threshold answers "is this much data in this window unusual", not "what did
 * it cost".
 */
class ExposureService
{
    public function weightFor(?DocumentClassification $tier): int
    {
        $key = $tier?->value ?? 'restricted';

        return (int) config("access_governance.exposure.weights.{$key}", 0);
    }

    public function consumedBy(string $subjectType, string $subjectLabel, ?CarbonInterface $since = null): int
    {
        $since ??= now()->subMinutes((int) config('access_governance.exposure.window_minutes', 60));

        // Only access that returned data consumes exposure. A denied request read
        // nothing, and counting it would let a noisy or misconfigured enforcement
        // point push an innocent subject into step-up.
        return (int) AccessDecision::query()
            ->where('subject_type', $subjectType)
            ->where('subject_label', $subjectLabel)
            ->where('decided_at', '>=', $since)
            ->whereIn('action', [
                AccessAction::Allow->value,
                AccessAction::AllowAudited->value,
                AccessAction::TimeBoxed->value,
            ])
            ->sum('risk_weight');
    }

    /**
     * @return array{limit: int, remaining: int, band: string}
     */
    public function position(string $subjectType, string $subjectLabel): array
    {
        $limit = (int) config('access_governance.exposure.thresholds.block', 100);
        $consumed = $this->consumedBy($subjectType, $subjectLabel);

        return [
            'limit' => $limit,
            'remaining' => max(0, $limit - $consumed),
            'band' => $this->band($consumed),
        ];
    }

    public function band(int $weight): string
    {
        $thresholds = $this->thresholds();

        return match (true) {
            $weight >= $thresholds['block'] => 'block',
            $weight >= $thresholds['step_up'] => 'step_up',
            $weight >= $thresholds['review'] => 'review',
            default => 'normal',
        };
    }

    /**
     * @return array{review: int, step_up: int, block: int}
     */
    public function thresholds(): array
    {
        return [
            'review' => (int) config('access_governance.exposure.thresholds.review', 25),
            'step_up' => (int) config('access_governance.exposure.thresholds.step_up', 50),
            'block' => (int) config('access_governance.exposure.thresholds.block', 100),
        ];
    }
}
