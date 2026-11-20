<?php

namespace App\Services\Assessment;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Models\AssessmentReport;
use App\Models\Finding;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

/**
 * Import a SARIF 2.1 report and create or update findings.
 *
 * The report file stays in the CI or document store. OpenGRC keeps the hash, the
 * tool metadata and one deduplicated finding per result. Code snippets in the
 * SARIF are never read or stored.
 */
class SarifImporter
{
    /**
     * @return array{name: string, tool: string, tool_version: string|null, finding_count: int}
     */
    public function preview(string $path): array
    {
        $data = $this->decode($path);
        $tool = $this->tool($data);

        return [
            'name' => $tool['name'].' assessment',
            'tool' => $tool['name'],
            'tool_version' => $tool['version'],
            'finding_count' => $this->resultCount($data),
        ];
    }

    public function import(string $path, ?int $applicationId = null, ?int $assetId = null): AssessmentReport
    {
        $data = $this->decode($path);
        $hash = hash_file('sha256', $path);

        if (! is_string($hash)) {
            throw new InvalidArgumentException('The SARIF file could not be hashed.');
        }

        $tool = $this->tool($data);

        return DB::transaction(function () use ($data, $hash, $applicationId, $assetId, $tool): AssessmentReport {
            $report = AssessmentReport::query()->firstOrNew([
                'report_sha256' => $hash,
                'application_id' => $applicationId,
                'asset_id' => $assetId,
            ]);

            $report->fill([
                'name' => $tool['name'].' assessment',
                'format' => 'sarif_json',
                'tool' => $tool['name'],
                'tool_version' => $tool['version'],
                'application_id' => $applicationId,
                'asset_id' => $assetId,
                'metadata' => [
                    'runs' => count($this->runs($data)),
                    'results' => $this->resultCount($data),
                ],
                'imported_at' => now(),
            ])->save();

            $count = 0;

            foreach ($this->runs($data) as $run) {
                foreach ($this->results($run) as $result) {
                    $this->storeFinding($report, $tool['name'], $result);
                    $count++;
                }
            }

            $report->forceFill(['finding_count' => $count])->save();

            return $report->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function storeFinding(AssessmentReport $report, string $tool, array $result): void
    {
        $ruleId = $this->string($result['ruleId'] ?? null)
            ?? $this->string($result['rule']['id'] ?? null)
            ?? 'unknown';
        $message = $this->string($result['message']['text'] ?? null) ?? 'Scanner finding.';
        $level = $this->string($result['level'] ?? null) ?? 'warning';
        $severity = $this->severity($result, $level);
        $location = $this->location($result);
        $dedupeKey = 'sarif:'.$tool.':'.$ruleId.':'.($location['uri'] ?? 'global').':'.($location['line'] ?? 0);

        $finding = Finding::query()->firstOrNew(['dedupe_key' => $dedupeKey]);
        $created = ! $finding->exists;
        $status = (! $created && $finding->status === FindingStatus::Accepted)
            ? FindingStatus::Accepted
            : FindingStatus::Open;

        $description = trim($message)
            ."\n\nTool: {$tool}\nRule: {$ruleId}";

        if ($location['uri'] !== null) {
            $description .= "\nLocation: {$location['uri']}".($location['line'] === null ? '' : ':'.$location['line']);
        }

        $finding->fill([
            'title' => Str::limit($ruleId.': '.$message, 250),
            'description' => $description,
            'severity' => $severity,
            'status' => $status,
            'source' => FindingSource::Automated,
            'asset_id' => $report->asset_id,
            'assessment_report_id' => $report->getKey(),
            'owner_id' => $finding->owner_id,
            'dedupe_key' => $dedupeKey,
            'occurrences' => ($finding->occurrences ?? 0) + 1,
            'identified_at' => $finding->identified_at ?? now(),
            'last_seen_at' => now(),
            'due_at' => $finding->due_at ?? $this->dueAt($severity),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{uri: string|null, line: int|null}
     */
    protected function location(array $result): array
    {
        $physical = $result['locations'][0]['physicalLocation'] ?? null;

        if (! is_array($physical)) {
            return ['uri' => null, 'line' => null];
        }

        $artifact = is_array($physical['artifactLocation'] ?? null) ? $physical['artifactLocation'] : [];
        $region = is_array($physical['region'] ?? null) ? $physical['region'] : [];
        $line = $region['startLine'] ?? null;

        return [
            'uri' => $this->string($artifact['uri'] ?? null),
            'line' => is_numeric($line) ? (int) $line : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function severity(array $result, string $level): EvidenceSeverity
    {
        $properties = is_array($result['properties'] ?? null) ? $result['properties'] : [];
        $label = $this->string($properties['severity'] ?? null);

        if ($label !== null) {
            return match (strtolower($label)) {
                'critical' => EvidenceSeverity::Critical,
                'high', 'error' => EvidenceSeverity::High,
                'medium', 'moderate', 'warning' => EvidenceSeverity::Medium,
                'low', 'note', 'info', 'informational' => EvidenceSeverity::Low,
                default => EvidenceSeverity::Medium,
            };
        }

        return match (strtolower($level)) {
            'error' => EvidenceSeverity::High,
            'note', 'none' => EvidenceSeverity::Low,
            default => EvidenceSeverity::Medium,
        };
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

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("The SARIF file is not readable: {$path}.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The SARIF file is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('The SARIF document must be a JSON object.');
        }

        $version = $this->string($data['version'] ?? null);

        if ($version === null || ! str_starts_with($version, '2.')) {
            throw new InvalidArgumentException('Only SARIF 2.x is supported.');
        }

        if (isset($data['runs']) && ! is_array($data['runs'])) {
            throw new InvalidArgumentException('The SARIF runs field must be a list.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function runs(array $data): array
    {
        return array_values(array_filter($data['runs'] ?? [], 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<int, array<string, mixed>>
     */
    private function results(array $run): array
    {
        return array_values(array_filter($run['results'] ?? [], 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resultCount(array $data): int
    {
        $count = 0;

        foreach ($this->runs($data) as $run) {
            $count += count($this->results($run));
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, version: string|null}
     */
    private function tool(array $data): array
    {
        $first = $this->runs($data)[0] ?? [];
        $driver = is_array($first['tool']['driver'] ?? null) ? $first['tool']['driver'] : [];

        return [
            'name' => $this->string($driver['name'] ?? null) ?? 'SARIF',
            'version' => $this->string($driver['version'] ?? null),
        ];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
