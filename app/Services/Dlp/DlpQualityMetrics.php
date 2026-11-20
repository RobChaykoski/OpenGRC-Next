<?php

namespace App\Services\Dlp;

use App\Models\DlpFinding;
use Illuminate\Support\Collection;

/**
 * False-positive quality by DLP identifier type.
 *
 * Only reviewed findings are counted. A type with fewer reviewed findings than
 * its minimum sample is "insufficient data", not "on target". The target is a
 * programme target with a documented reference; there is no universal industry
 * false-positive rate.
 */
class DlpQualityMetrics
{
    public function __construct(protected DlpIdentifierCatalog $catalog) {}

    /**
     * @return array{window_days: int, reviewed: int, false_positives: int, rate: float|null, targets_met: int, targets_missed: int, insufficient: int, rows: array<int, array{code: string, label: string, category: string, reviewed: int, false_positives: int, rate: float|null, target: float, minimum_sample: int, reference: string, status: string}>}
     */
    public function summary(int $days = 90): array
    {
        $days = max(1, $days);

        /** @var Collection<int, DlpFinding> $findings */
        $findings = DlpFinding::query()
            ->whereNotNull('disposition')
            ->where('reviewed_at', '>=', now()->subDays($days))
            ->get();

        $rows = [];
        $targetsMet = 0;
        $targetsMissed = 0;
        $insufficient = 0;

        foreach ($this->catalog->definitions() as $code => $definition) {
            $matching = $findings->filter(function (DlpFinding $finding) use ($code, $definition): bool {
                $codes = is_array($finding->identifier_types) ? $finding->identifier_types : [];
                $labels = is_array($finding->match_types) ? $finding->match_types : [];

                return in_array($code, $codes, true)
                    || array_intersect($labels, $definition['match_labels']) !== [];
            });

            $reviewed = $matching->count();
            $falsePositives = $matching->filter(fn (DlpFinding $finding): bool => $finding->isFalsePositive())->count();
            $rate = $reviewed > 0 ? round($falsePositives / $reviewed * 100, 1) : null;
            $status = $this->status($reviewed, $rate, $definition);

            match ($status) {
                'on_target' => $targetsMet++,
                'above_target' => $targetsMissed++,
                'insufficient' => $insufficient++,
                default => null,
            };

            $rows[] = [
                'code' => $code,
                'label' => $definition['label'],
                'category' => $definition['category'],
                'reviewed' => $reviewed,
                'false_positives' => $falsePositives,
                'rate' => $rate,
                'target' => $definition['target_false_positive_rate'],
                'minimum_sample' => $definition['minimum_sample'],
                'reference' => $definition['reference'],
                'status' => $status,
            ];
        }

        $overallReviewed = $findings->count();
        $overallFalsePositives = $findings->filter(fn (DlpFinding $finding): bool => $finding->isFalsePositive())->count();

        return [
            'window_days' => $days,
            'reviewed' => $overallReviewed,
            'false_positives' => $overallFalsePositives,
            'rate' => $overallReviewed > 0 ? round($overallFalsePositives / $overallReviewed * 100, 1) : null,
            'targets_met' => $targetsMet,
            'targets_missed' => $targetsMissed,
            'insufficient' => $insufficient,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{label: string, category: string, match_labels: array<int, string>, target_false_positive_rate: float, minimum_sample: int, reference: string}  $definition
     */
    protected function status(int $reviewed, ?float $rate, array $definition): string
    {
        if ($reviewed < $definition['minimum_sample']) {
            return 'insufficient';
        }

        if ($rate === null) {
            return 'insufficient';
        }

        return $rate <= $definition['target_false_positive_rate'] ? 'on_target' : 'above_target';
    }
}
