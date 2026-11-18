<?php

namespace App\Services\Scoping;

use App\Models\CanonicalFramework;

/**
 * Component-visible assurance score for one framework.
 *
 * The score is a weighted summary of review and evidence quality. It is not a
 * compliance percentage and must never be shown without its components, because
 * a single number hides which work is missing (ADR 0015).
 */
final class AssuranceScore
{
    /**
     * Weights sum to 100.
     *
     * @var array<string, int>
     */
    public const WEIGHTS = [
        'reviewed' => 25,
        'evidence' => 30,
        'quality' => 20,
        'automation' => 15,
        'monitoring' => 10,
    ];

    public function __construct(private readonly CanonicalOutcomeCoverage $coverage) {}

    /**
     * @return array{
     *     framework: string,
     *     name: string,
     *     outcomes: int,
     *     counts: array{reviewed: int, evidenced: int, automated: int, monitored: int},
     *     components: array<string, array{weight: int, value: int, weighted: float}>,
     *     score: int,
     *     computed_at: string
     * }|null
     */
    public function forFramework(string $code): ?array
    {
        $framework = CanonicalFramework::query()->where('code', $code)->first();

        if ($framework === null) {
            return null;
        }

        $rows = $this->coverage->summary($code);

        if ($rows === []) {
            return null;
        }

        $required = count($rows);
        $reviewed = 0;
        $evidenced = 0;
        $automated = 0;
        $monitored = 0;
        $confidenceTotal = 0;

        foreach ($rows as $row) {
            if ($row['controls'] > 0) {
                $reviewed++;
            }

            if ($row['has_evidence']) {
                $evidenced++;
                $confidenceTotal += $row['best_confidence'];
            }

            if ($row['automated']) {
                $automated++;
            }

            if ($row['monitored']) {
                $monitored++;
            }
        }

        $percentage = static fn (int $count): int => (int) round($count / $required * 100);

        $values = [
            'reviewed' => $percentage($reviewed),
            'evidence' => $percentage($evidenced),
            'quality' => $evidenced === 0 ? 0 : (int) round($confidenceTotal / $evidenced),
            'automation' => $percentage($automated),
            'monitoring' => $percentage($monitored),
        ];

        $components = [];
        $total = 0.0;

        foreach (self::WEIGHTS as $key => $weight) {
            $value = $values[$key];
            $weighted = round($value * $weight / 100, 1);

            $components[$key] = [
                'weight' => $weight,
                'value' => $value,
                'weighted' => $weighted,
            ];

            $total += $weighted;
        }

        return [
            'framework' => $framework->code,
            'name' => $framework->name,
            'outcomes' => $required,
            'counts' => [
                'reviewed' => $reviewed,
                'evidenced' => $evidenced,
                'automated' => $automated,
                'monitored' => $monitored,
            ],
            'components' => $components,
            'score' => (int) round($total),
            'computed_at' => now()->toIso8601String(),
        ];
    }
}
