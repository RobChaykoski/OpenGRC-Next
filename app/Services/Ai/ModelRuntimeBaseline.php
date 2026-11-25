<?php

namespace App\Services\Ai;

use App\Enums\EvidenceSeverity;

/**
 * Compare a recorded inference runtime against the configured baseline
 * (ADR 0059, section 5).
 *
 * The baseline is deployment configuration, not a vendor claim: it lives in
 * config/ai_models.php and an operator reviews it. A runtime that is absent, a
 * version below the minimum or above the maximum, and a version that cannot be
 * compared all produce the same medium finding. Absence of a runtime is not a
 * finding: the register records no runtime rather than guessing one.
 */
final class ModelRuntimeBaseline
{
    /**
     * @return array{severity: EvidenceSeverity, title: string, description: string}|null
     */
    public function finding(string $name, ?string $runtime, ?string $version): ?array
    {
        $runtime = is_string($runtime) ? strtolower(trim($runtime)) : '';

        if ($runtime === '') {
            return null;
        }

        $baselines = config('ai_models.runtime_baseline', []);
        $baseline = is_array($baselines) && isset($baselines[$runtime]) && is_array($baselines[$runtime])
            ? $baselines[$runtime]
            : null;

        $label = trim($runtime.' '.trim((string) $version));

        if ($baseline === null) {
            return $this->definition(
                $name,
                "runtime {$label} is not in the configured supported baseline",
                'Record the runtime in config/ai_models.php at the version the institution supports, or move the artifact to a supported runtime.',
            );
        }

        $hasMaximum = filled($baseline['max'] ?? null);
        $minimum = $this->comparableVersion($baseline['min'] ?? null);
        $maximum = $this->comparableVersion($baseline['max'] ?? null);

        if ($minimum === null || ($hasMaximum && $maximum === null)) {
            return $this->definition(
                $name,
                "the supported baseline for runtime {$runtime} is not a comparable version",
                'Correct config/ai_models.php: each runtime baseline needs a numeric min and, if set, a numeric max.',
            );
        }

        $candidate = $this->comparableVersion($version);

        if ($candidate === null) {
            return $this->definition(
                $name,
                "runtime {$runtime} was recorded without a comparable version",
                'Record the runtime version as a numeric version or a llama.cpp build such as b3600, so it can be compared with the supported baseline.',
            );
        }

        if (version_compare($candidate, $minimum, '<')) {
            return $this->definition(
                $name,
                "runtime {$label} is below the supported minimum {$minimum}",
                'Upgrade or pin the runtime to a supported version, or update config/ai_models.php with a reviewed baseline.',
            );
        }

        if ($hasMaximum && $maximum !== null && version_compare($candidate, $maximum, '>')) {
            return $this->definition(
                $name,
                "runtime {$label} is above the supported maximum {$maximum}",
                'Pin the runtime to a supported version, or update config/ai_models.php with a reviewed baseline.',
            );
        }

        return null;
    }

    /**
     * @return array{severity: EvidenceSeverity, title: string, description: string}
     */
    private function definition(string $name, string $condition, string $remediation): array
    {
        return [
            'severity' => EvidenceSeverity::Medium,
            'title' => "Unsupported inference runtime: {$name}",
            'description' => ucfirst($condition).'. '.$remediation,
        ];
    }

    /**
     * Accept a numeric version, an optional leading v, or a llama.cpp build
     * such as b3600. Anything else cannot be shown to be inside a range.
     */
    private function comparableVersion(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^[vV]?(\d+(?:\.\d+)*)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^b(\d+)$/i', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
