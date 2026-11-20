<?php

namespace App\Services\SupplyChain;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Enums\SoftwareVulnerabilityStatus;
use App\Models\Finding;
use App\Models\SbomDocument;
use App\Models\SoftwareComponent;
use App\Models\SoftwareVulnerability;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Query OSV for the components in an SBOM and turn advisory hits into findings.
 *
 * A re-scan is idempotent by component and OSV id. A resolved finding is not
 * deleted; a new scan reopens it if the vulnerable component is still present.
 */
class SbomScanner
{
    public function __construct(protected OsvClient $client) {}

    /**
     * @return array{
     *     components: int,
     *     queried: int,
     *     unsupported: int,
     *     vulnerabilities: int,
     *     findings_created: int,
     *     findings_updated: int,
     *     errors: array<int, string>
     * }
     */
    public function scan(SbomDocument $document): array
    {
        $components = $document->components()
            ->whereNotNull('purl')
            ->whereNotNull('version')
            ->get();

        $queries = $this->client->queries($components);
        $batchSize = max(1, min(1000, (int) config('osv.batch_size', 500)));
        $details = [];

        $counts = [
            'components' => $components->count(),
            'queried' => 0,
            'unsupported' => $components->count() - count($queries),
            'vulnerabilities' => 0,
            'findings_created' => 0,
            'findings_updated' => 0,
            'errors' => [],
        ];

        foreach (array_chunk($queries, $batchSize) as $chunk) {
            $results = $this->client->queryBatch(array_column($chunk, 'query'));

            foreach ($chunk as $index => $item) {
                $counts['queried']++;
                $result = is_array($results[$index] ?? null) ? $results[$index] : [];
                $vulns = is_array($result['vulns'] ?? null) ? $result['vulns'] : [];

                foreach ($vulns as $vuln) {
                    $id = is_array($vuln) ? ($vuln['id'] ?? null) : null;

                    if (! is_string($id) || $id === '') {
                        continue;
                    }

                    try {
                        $details[$id] ??= $this->client->vulnerability($id);
                        $this->store($document, $item['component'], $details[$id], $counts);
                    } catch (Throwable $exception) {
                        if (count($counts['errors']) < 5) {
                            $counts['errors'][] = "{$id}: {$exception->getMessage()}";
                        }
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $advisory
     * @param  array<string, mixed>  $counts
     */
    protected function store(
        SbomDocument $document,
        SoftwareComponent $component,
        array $advisory,
        array &$counts,
    ): void {
        $osvId = is_string($advisory['id'] ?? null) ? $advisory['id'] : null;

        if ($osvId === null) {
            return;
        }

        $severity = $this->severity($advisory);
        $dedupeKey = 'osv:'.$osvId.':'.$component->component_key;

        $finding = Finding::query()->firstOrNew(['dedupe_key' => $dedupeKey]);
        $created = ! $finding->exists;

        if (! $created && $finding->status === FindingStatus::Accepted) {
            $status = FindingStatus::Accepted;
        } else {
            $status = FindingStatus::Open;
        }

        $finding->fill([
            'title' => "OSV {$osvId} in {$component->displayName()}",
            'description' => $this->description($advisory, $component),
            'severity' => $severity,
            'status' => $status,
            'source' => FindingSource::Automated,
            'asset_id' => $document->asset_id,
            'owner_id' => $finding->owner_id,
            'dedupe_key' => $dedupeKey,
            'occurrences' => ($finding->occurrences ?? 0) + 1,
            'identified_at' => $finding->identified_at ?? now(),
            'last_seen_at' => now(),
            'due_at' => $finding->due_at ?? $this->dueAt($severity),
        ])->save();

        $vulnerability = SoftwareVulnerability::query()->firstOrNew([
            'software_component_id' => $component->getKey(),
            'osv_id' => $osvId,
        ]);

        $vulnerability->fill([
            'finding_id' => $finding->getKey(),
            'aliases' => is_array($advisory['aliases'] ?? null) ? $advisory['aliases'] : null,
            'summary' => $this->string($advisory['summary'] ?? null),
            'details' => $this->string($advisory['details'] ?? null),
            'severity' => $severity,
            'affected' => $this->affected($advisory),
            'fixed_version' => $this->fixedVersion($advisory),
            'published_at' => $this->date($advisory['published'] ?? null),
            'modified_at' => $this->date($advisory['modified'] ?? null),
            'source_url' => 'https://osv.dev/vulnerability/'.$osvId,
            'status' => SoftwareVulnerabilityStatus::Open,
            'first_seen_at' => $vulnerability->first_seen_at ?? now(),
            'last_seen_at' => now(),
        ])->save();

        $counts['vulnerabilities']++;

        if ($created) {
            $counts['findings_created']++;
        } else {
            $counts['findings_updated']++;
        }
    }

    /**
     * @param  array<string, mixed>  $advisory
     */
    protected function severity(array $advisory): EvidenceSeverity
    {
        $databaseSeverity = $advisory['database_specific']['severity'] ?? null;
        $ecosystemSeverity = $advisory['affected'][0]['ecosystem_specific']['severity'] ?? null;
        $label = is_string($databaseSeverity) ? $databaseSeverity : (is_string($ecosystemSeverity) ? $ecosystemSeverity : null);

        if ($label !== null) {
            return match (strtolower($label)) {
                'critical' => EvidenceSeverity::Critical,
                'high' => EvidenceSeverity::High,
                'moderate', 'medium' => EvidenceSeverity::Medium,
                'low' => EvidenceSeverity::Low,
                default => EvidenceSeverity::Medium,
            };
        }

        $score = $advisory['severity'][0]['score'] ?? null;

        if (is_numeric($score)) {
            $value = (float) $score;

            return match (true) {
                $value >= 9.0 => EvidenceSeverity::Critical,
                $value >= 7.0 => EvidenceSeverity::High,
                $value >= 4.0 => EvidenceSeverity::Medium,
                default => EvidenceSeverity::Low,
            };
        }

        return EvidenceSeverity::Medium;
    }

    /**
     * @param  array<string, mixed>  $advisory
     */
    protected function description(array $advisory, SoftwareComponent $component): string
    {
        $summary = $this->string($advisory['summary'] ?? null) ?? 'Vulnerable dependency.';
        $fixed = $this->fixedVersion($advisory);

        return trim($summary
            .' Component: '.$component->displayName().'.'
            .($fixed === null ? '' : ' Fixed in '.$fixed.'.'));
    }

    /**
     * @param  array<string, mixed>  $advisory
     */
    protected function fixedVersion(array $advisory): ?string
    {
        $affected = is_array($advisory['affected'] ?? null) ? $advisory['affected'] : [];

        foreach ($affected as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $ranges = is_array($entry['ranges'] ?? null) ? $entry['ranges'] : [];

            foreach ($ranges as $range) {
                if (! is_array($range)) {
                    continue;
                }

                $events = is_array($range['events'] ?? null) ? $range['events'] : [];

                foreach ($events as $event) {
                    if (is_array($event) && is_string($event['fixed'] ?? null) && $event['fixed'] !== '') {
                        return $event['fixed'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $advisory
     */
    protected function affected(array $advisory): ?string
    {
        $affected = is_array($advisory['affected'] ?? null) ? $advisory['affected'] : [];
        $parts = [];

        foreach ($affected as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $package = is_array($entry['package'] ?? null) ? $entry['package'] : [];
            $ecosystem = $this->string($package['ecosystem'] ?? null);
            $name = $this->string($package['name'] ?? null);

            if ($ecosystem !== null || $name !== null) {
                $parts[] = trim(($ecosystem ?? '').'/'.($name ?? ''), '/');
            }
        }

        return $parts === [] ? null : implode('; ', array_slice(array_unique($parts), 0, 10));
    }

    protected function dueAt(EvidenceSeverity $severity): CarbonImmutable
    {
        return match ($severity) {
            EvidenceSeverity::Critical => CarbonImmutable::now()->addDays(7),
            EvidenceSeverity::High => CarbonImmutable::now()->addDays(30),
            EvidenceSeverity::Medium => CarbonImmutable::now()->addDays(60),
            EvidenceSeverity::Low => CarbonImmutable::now()->addDays(90),
        };
    }

    protected function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
