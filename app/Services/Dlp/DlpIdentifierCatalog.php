<?php

namespace App\Services\Dlp;

/**
 * The measured identifier types and their programme targets.
 *
 * The scanner reports human-readable labels. This catalog maps those labels to
 * stable codes so a false-positive rate can be measured even when a scanner
 * changes its wording.
 */
class DlpIdentifierCatalog
{
    /**
     * @return array<string, array{label: string, category: string, match_labels: array<int, string>, target_false_positive_rate: float, minimum_sample: int, reference: string}>
     */
    public function definitions(): array
    {
        /** @var array<string, array{label: string, category: string, match_labels: array<int, string>, target_false_positive_rate: float, minimum_sample: int, reference: string}> $definitions */
        $definitions = config('dlp.identifier_types', []);

        return is_array($definitions) ? $definitions : [];
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    public function codesForLabels(array $labels): array
    {
        $codes = [];

        foreach ($this->definitions() as $code => $definition) {
            if (array_intersect($labels, $definition['match_labels']) !== []) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return array{label: string, category: string, match_labels: array<int, string>, target_false_positive_rate: float, minimum_sample: int, reference: string}|null
     */
    public function definition(string $code): ?array
    {
        return $this->definitions()[$code] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function labelsFor(string $code): array
    {
        return $this->definition($code)['match_labels'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->definitions() as $code => $definition) {
            $options[$code] = $definition['label'];
        }

        return $options;
    }
}
