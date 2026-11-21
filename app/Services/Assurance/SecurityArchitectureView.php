<?php

namespace App\Services\Assurance;

use App\Models\CanonicalControl;
use App\Services\Scoping\CanonicalOutcomeCoverage;

/**
 * Read-only security architecture view (ADR 0048).
 *
 * It places canonical outcomes into NIST CSF 2.0 functions and reports the
 * current coverage state. It deliberately does not invent a target state or a
 * deployment location: those are programme decisions recorded in treatment,
 * acceptance and the architecture document.
 */
final class SecurityArchitectureView
{
    public function __construct(private readonly CanonicalOutcomeCoverage $coverage) {}

    /**
     * @return array{
     *     framework: string,
     *     functions: array<int, array{key: string, outcomes: array<int, array<string, mixed>>, counts: array<string, int>}>,
     *     counts: array<string, int>,
     *     outcomes: int
     * }
     */
    public function build(string $frameworkCode): array
    {
        $rows = $this->coverage->summary($frameworkCode);

        $domains = CanonicalControl::query()
            ->whereIn('code', array_column($rows, 'code'))
            ->pluck('risk_domain', 'code');

        $functions = [];

        foreach ((array) config('security_architecture.functions', []) as $key => $definition) {
            $functions[$key] = [
                'key' => $key,
                'outcomes' => [],
                'counts' => $this->emptyCounts(),
            ];
        }

        $totals = $this->emptyCounts();

        foreach ($rows as $row) {
            $domain = $domains[$row['code']] ?? $row['category'] ?? null;
            $functionKey = $this->functionFor($domain);
            $state = $this->state($row);

            $entry = [
                'code' => $row['code'],
                'name' => $row['name'],
                'category' => $row['category'],
                'priority' => $row['priority'],
                'controls' => $row['controls'],
                'policies' => $row['policies'],
                'evidence' => $row['evidence'],
                'validations' => $row['validations'],
                'latest_evidence_at' => $row['latest_evidence_at'],
                'latest_validation_status' => $row['latest_validation_status'],
                'expected_interval_days' => $row['expected_interval_days'],
                'best_confidence' => $row['best_confidence'],
                'confidence_basis' => $row['confidence_basis'],
                'state' => $state,
            ];

            $functions[$functionKey]['outcomes'][] = $entry;
            $functions[$functionKey]['counts']['outcomes']++;
            $functions[$functionKey]['counts'][$state]++;
            $totals['outcomes']++;
            $totals[$state]++;
        }

        foreach ($functions as &$function) {
            usort($function['outcomes'], fn (array $left, array $right): int => [$left['priority'], $left['code']] <=> [$right['priority'], $right['code']]);
        }

        unset($function);

        return [
            'framework' => $frameworkCode,
            'functions' => array_values($functions),
            'counts' => $totals,
            'outcomes' => $totals['outcomes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function state(array $row): string
    {
        if ((int) $row['controls'] === 0) {
            return 'gap';
        }

        if (! $row['has_evidence']) {
            return ((int) $row['policies'] > 0) ? 'documented' : 'gap';
        }

        if (($row['latest_validation_status'] ?? null) === 'failed') {
            return 'failing';
        }

        if ($row['stale']) {
            return 'stale';
        }

        return 'evidenced';
    }

    private function functionFor(?string $domain): string
    {
        foreach ((array) config('security_architecture.functions', []) as $key => $definition) {
            if (in_array($domain, (array) ($definition['domains'] ?? []), true)) {
                return $key;
            }
        }

        return (string) config('security_architecture.default_function', 'protect');
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'outcomes' => 0,
            'evidenced' => 0,
            'documented' => 0,
            'stale' => 0,
            'failing' => 0,
            'gap' => 0,
        ];
    }
}
