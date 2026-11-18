<?php

namespace App\Services\Assurance;

use App\Services\Scoping\CanonicalOutcomeCoverage;

/**
 * Outcomes that need attention, split by reason.
 *
 * Staleness follows the catalogue's frequency rather than a single global
 * threshold: a weekly control and an annual control do not age at the same rate.
 */
final class FrameworkGapReport
{
    public function __construct(private readonly CanonicalOutcomeCoverage $coverage) {}

    /**
     * @return array{
     *     framework: string,
     *     outcomes: int,
     *     unreviewed: array<int, array<string, mixed>>,
     *     no_evidence: array<int, array<string, mixed>>,
     *     stale_evidence: array<int, array<string, mixed>>,
     *     failing_validation: array<int, array<string, mixed>>
     * }
     */
    public function forFramework(string $frameworkCode): array
    {
        $rows = $this->coverage->summary($frameworkCode);

        $report = [
            'framework' => $frameworkCode,
            'outcomes' => count($rows),
            'unreviewed' => [],
            'no_evidence' => [],
            'stale_evidence' => [],
            'failing_validation' => [],
        ];

        foreach ($rows as $row) {
            $entry = [
                'code' => $row['code'],
                'name' => $row['name'],
                'priority' => $row['priority'],
                'controls' => $row['controls'],
                'evidence' => $row['evidence'],
                'validations' => $row['validations'],
                'latest_evidence_at' => $row['latest_evidence_at'],
                'latest_validation_status' => $row['latest_validation_status'],
                'expected_interval_days' => $row['expected_interval_days'],
            ];

            if ($row['controls'] === 0) {
                $report['unreviewed'][] = $entry;

                continue;
            }

            if (! $row['has_evidence']) {
                $report['no_evidence'][] = $entry;

                continue;
            }

            if ($row['stale']) {
                $report['stale_evidence'][] = $entry;
            }

            if ($row['latest_validation_status'] === 'failed') {
                $report['failing_validation'][] = $entry;
            }
        }

        return $report;
    }

    /**
     * One row per outcome that appears in any gap category, with its reasons.
     *
     * @return array<int, array{code: string, name: string, reasons: array<int, string>}>
     */
    public function condensed(string $frameworkCode): array
    {
        $report = $this->forFramework($frameworkCode);
        $rows = [];

        foreach (['unreviewed', 'no_evidence', 'stale_evidence', 'failing_validation'] as $reason) {
            foreach ($report[$reason] as $entry) {
                $rows[$entry['code']] ??= [
                    'code' => $entry['code'],
                    'name' => $entry['name'],
                    'reasons' => [],
                ];

                $rows[$entry['code']]['reasons'][] = $reason;
            }
        }

        ksort($rows);

        return array_values($rows);
    }
}
