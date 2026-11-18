<?php

namespace App\Services\Scoping;

use Illuminate\Support\Collection;

final class FrameworkScopingService
{
    public function __construct(private readonly DataClassificationCatalog $classifications) {}

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function assess(array $profile): array
    {
        $industry = $this->industry($profile);
        $regions = $this->regions($profile);
        $labels = $this->labels($profile);
        $unknown = $this->classifications->unknownCodes($labels, $industry);

        $frameworks = collect(config('framework_scoping.frameworks', []))
            ->map(fn (array $definition, string $code) => $this->evaluate($code, $definition, $industry, $regions, $labels))
            ->filter(fn (array $row) => $row['applicable'])
            ->sortBy([['priority_rank', 'desc'], ['name', 'asc']])
            ->values();

        $controls = $this->controls($frameworks);
        $documents = $this->documents($frameworks);
        $evidence = $this->evidence($controls);

        return [
            'organization' => [
                'name' => $profile['name'] ?? null,
                'industry' => $industry,
                'regions' => $regions,
                'employee_band' => $profile['employees'] ?? null,
                'revenue_band' => $profile['revenue'] ?? null,
            ],
            'data_classifications' => $this->classifications->describe($labels, $industry),
            'framework_matrix' => $frameworks->map(fn (array $row) => [
                'code' => $row['code'],
                'framework' => $row['name'],
                'category' => $row['category'],
                'applicable' => true,
                'priority' => $row['priority'],
                'confidence' => $row['confidence'],
                'justification' => $row['justification'],
                'drivers' => $row['drivers'],
            ])->all(),
            'recommended_frameworks' => $frameworks->pluck('name')->all(),
            'framework_overlap' => $controls->map(fn (array $control) => [
                'control_id' => $control['code'],
                'control' => $control['name'],
                'frameworks' => $control['frameworks'],
                'framework_count' => count($control['frameworks']),
            ])->sortByDesc('framework_count')->values()->all(),
            'controls' => $controls->values()->all(),
            'documents' => $documents->values()->all(),
            'evidence' => $evidence->values()->all(),
            'evidence_sources' => $this->sources($controls),
            'automation_candidates' => $evidence
                ->filter(fn (array $row) => in_array($row['automation_readiness'], ['high', 'medium'], true))
                ->values()
                ->all(),
            'maturity_roadmap' => $this->roadmap($frameworks, $documents, $controls),
            'gaps' => $this->gaps($profile, $industry, $regions, $labels, $unknown, $frameworks),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $regions
     * @param  array<int, string>  $labels
     * @return array<string, mixed>
     */
    private function evaluate(string $code, array $definition, string $industry, array $regions, array $labels): array
    {
        $requiredRegions = $definition['regions'] ?? [];
        $requiredIndustries = $definition['industries'] ?? [];
        $requiredLabels = $definition['labels'] ?? [];

        $regionMatch = $requiredRegions === [] || array_intersect($requiredRegions, $regions) !== [];
        $industryMatch = $requiredIndustries === [] || in_array($industry, $requiredIndustries, true);
        $labelMatch = $requiredLabels === [] || array_intersect($requiredLabels, $labels) !== [];

        $applicable = $regionMatch && $industryMatch && $labelMatch;
        $specificity = count(array_filter([$requiredRegions, $requiredIndustries, $requiredLabels]));

        $reasons = [];

        if ($requiredRegions !== []) {
            $reasons[] = 'region';
        }
        if ($requiredIndustries !== []) {
            $reasons[] = 'industry';
        }
        if ($requiredLabels !== []) {
            $reasons[] = 'data classification';
        }

        $priorityRank = match ($definition['priority'] ?? 3) {
            1 => 3,
            2 => 2,
            default => 1,
        };

        return [
            'code' => $code,
            'name' => $definition['name'],
            'category' => $definition['category'] ?? 'Other',
            'priority' => match ($definition['priority'] ?? 3) {
                1 => 'high',
                2 => 'medium',
                default => 'low',
            },
            'priority_rank' => $priorityRank,
            'confidence' => match (true) {
                $specificity >= 2 => 'high',
                $specificity === 1 => 'medium',
                default => 'low',
            },
            'justification' => $reasons === []
                ? 'Universally applicable baseline.'
                : 'Matched on '.implode(', ', $reasons).'.',
            'drivers' => $definition['drivers'] ?? ['Business'],
            'applicable' => $applicable,
            'definition' => $definition,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $frameworks
     * @return Collection<string, array<string, mixed>>
     */
    private function controls(Collection $frameworks): Collection
    {
        $catalogue = config('framework_scoping.controls', []);
        $controls = collect();

        foreach ($frameworks as $framework) {
            foreach ($framework['definition']['controls'] ?? [] as $code) {
                if (! isset($catalogue[$code])) {
                    continue;
                }

                $control = $controls->get($code, [
                    'code' => $code,
                    'name' => $catalogue[$code]['name'],
                    'category' => $catalogue[$code]['category'],
                    'risk_domain' => $catalogue[$code]['risk_domain'],
                    'priority' => $catalogue[$code]['priority'],
                    'frameworks' => [],
                ]);

                $control['frameworks'][] = $framework['name'];
                $controls->put($code, $control);
            }
        }

        return $controls->sortBy('code');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $frameworks
     * @return Collection<string, array<string, mixed>>
     */
    private function documents(Collection $frameworks): Collection
    {
        $register = config('framework_scoping.documents', []);
        $documents = collect();

        foreach ($frameworks as $framework) {
            foreach ($framework['definition']['documents'] ?? [] as $name) {
                $document = $documents->get($name, [
                    'document' => $name,
                    'frameworks' => [],
                    'required' => false,
                    'review_frequency' => $register[$name]['frequency'] ?? 'annual',
                ]);

                $document['frameworks'][] = $framework['name'];
                $document['required'] = $document['required'] || $framework['priority'] === 'high';
                $documents->put($name, $document);
            }
        }

        return $documents->sortKeys();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $controls
     * @return Collection<int, array<string, mixed>>
     */
    private function evidence(Collection $controls): Collection
    {
        $catalogue = config('framework_scoping.controls', []);

        return $controls->map(fn (array $control) => [
            'control_id' => $control['code'],
            'control' => $control['name'],
            'evidence_required' => $catalogue[$control['code']]['evidence'],
            'automation_readiness' => $catalogue[$control['code']]['automation'],
            'frequency' => $catalogue[$control['code']]['frequency'],
            'sources' => $catalogue[$control['code']]['sources'],
        ])->values();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $controls
     * @return array<int, array{control_id: string, source: string, readiness: string}>
     */
    private function sources(Collection $controls): array
    {
        $catalogue = config('framework_scoping.controls', []);
        $rows = [];

        foreach ($controls as $code => $control) {
            foreach ($catalogue[$code]['sources'] as $source) {
                $rows[] = [
                    'control_id' => $code,
                    'source' => $source,
                    'readiness' => $catalogue[$code]['automation'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $frameworks
     * @param  Collection<string, array<string, mixed>>  $documents
     * @param  Collection<string, array<string, mixed>>  $controls
     * @return array<int, array<string, mixed>>
     */
    private function roadmap(Collection $frameworks, Collection $documents, Collection $controls): array
    {
        $phases = config('framework_scoping.roadmap', []);

        return array_map(fn (array $phase) => $phase + [
            'status' => match ($phase['phase']) {
                1 => $documents->isNotEmpty() ? 'start now' : 'pending',
                2 => 'after documentation',
                3 => $controls->isNotEmpty() ? 'next' : 'pending',
                4 => 'requires evidence ledger',
                5 => 'requires connectors',
                default => 'later',
            },
        ], $phases);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, string>  $regions
     * @param  array<int, string>  $labels
     * @param  array<int, string>  $unknown
     * @param  Collection<int, array<string, mixed>>  $frameworks
     * @return array<int, string>
     */
    private function gaps(array $profile, string $industry, array $regions, array $labels, array $unknown, Collection $frameworks): array
    {
        $gaps = [];

        foreach ($unknown as $code) {
            $gaps[] = "Data classification '{$code}' is not available for industry '{$industry}'.";
        }

        if ($labels === []) {
            $gaps[] = 'No data classifications selected; privacy and payment frameworks cannot be scoped reliably.';
        }

        if ($regions === []) {
            $gaps[] = 'No operating region supplied; jurisdiction-specific frameworks are skipped.';
        }

        if ($industry === '') {
            $gaps[] = 'No industry supplied; industry-specific frameworks are skipped.';
        }

        if ($frameworks->isEmpty()) {
            $gaps[] = 'No framework matched the profile. Add a region, industry, or data classification.';
        }

        foreach (['name', 'employees', 'revenue'] as $field) {
            if (blank($profile[$field] ?? null)) {
                $gaps[] = "Profile field '{$field}' is missing; the recommendation is less precise.";
            }
        }

        return $gaps;
    }

    private function industry(array $profile): string
    {
        $value = $profile['industry'] ?? $profile['sector'] ?? '';

        return strtolower(trim((string) $value));
    }

    /**
     * @return array<int, string>
     */
    private function regions(array $profile): array
    {
        $regions = $profile['regions'] ?? [];

        if (! is_array($regions)) {
            $regions = [$regions];
        }

        foreach (['country', 'province', 'state'] as $field) {
            if (! blank($profile[$field] ?? null)) {
                $regions[] = (string) $profile[$field];
            }
        }

        $country = strtoupper(trim((string) ($profile['country'] ?? '')));
        $subdivision = strtoupper(trim((string) ($profile['province'] ?? $profile['state'] ?? '')));

        if ($country !== '' && $subdivision !== '') {
            $regions[] = $country.'-'.$subdivision;
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($region) => strtoupper(trim((string) $region)),
            $regions
        ))));
    }

    /**
     * @return array<int, string>
     */
    private function labels(array $profile): array
    {
        $labels = $profile['data_classifications'] ?? $profile['sensitive_data'] ?? [];

        if (! is_array($labels)) {
            $labels = [$labels];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($label) => strtolower(trim((string) $label)),
            $labels
        ))));
    }
}
