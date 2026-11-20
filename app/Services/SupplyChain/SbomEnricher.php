<?php

namespace App\Services\SupplyChain;

use App\Models\SbomDocument;
use Throwable;

/**
 * Enrich SBOM components with deps.dev licenses and project Scorecard data.
 *
 * This is optional context. A missing project or an internal package is skipped;
 * it does not change the vulnerability finding or the control verdict.
 */
class SbomEnricher
{
    public function __construct(protected DepsDevClient $client) {}

    /**
     * @return array{components: int, enriched: int, skipped: int, scorecards: int, failed: int, errors: array<int, string>}
     */
    public function enrich(SbomDocument $document): array
    {
        $components = $document->components()
            ->whereNotNull('purl')
            ->whereNotNull('version')
            ->get();

        $counts = [
            'components' => $components->count(),
            'enriched' => 0,
            'skipped' => 0,
            'scorecards' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($components as $component) {
            $query = $this->client->queryFor($component);

            if ($query === null) {
                $counts['skipped']++;

                continue;
            }

            try {
                $version = $this->client->packageVersion($query['system'], $query['name'], $query['version']);

                $component->fill([
                    'deps_dev_system' => $query['system'],
                    'deps_dev_checked_at' => now(),
                ]);

                if (blank($component->license) && is_array($version['licenses'] ?? null)) {
                    $licenses = array_values(array_filter($version['licenses'], 'is_string'));

                    if ($licenses !== []) {
                        $component->license = implode(' OR ', $licenses);
                    }
                }

                $projectId = $this->projectId($version);

                if ($projectId !== null) {
                    $project = $this->client->project($projectId);
                    $scorecard = is_array($project['scorecard'] ?? null) ? $project['scorecard'] : [];

                    if (is_numeric($scorecard['overallScore'] ?? null)) {
                        $component->scorecard_score = (float) $scorecard['overallScore'];
                        $counts['scorecards']++;
                    }

                    if (is_string($scorecard['date'] ?? null)) {
                        $component->scorecard_date = $scorecard['date'];
                    }
                }

                $component->save();
                $counts['enriched']++;
            } catch (Throwable $exception) {
                $counts['failed']++;

                if (count($counts['errors']) < 5) {
                    $counts['errors'][] = "{$component->displayName()}: {$exception->getMessage()}";
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $version
     */
    protected function projectId(array $version): ?string
    {
        $projects = $version['relatedProjects'] ?? null;

        if (! is_array($projects) || $projects === []) {
            return null;
        }

        $first = $projects[0];

        if (! is_array($first)) {
            return null;
        }

        $key = $first['projectKey'] ?? null;

        if (! is_array($key)) {
            return null;
        }

        $id = $key['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
