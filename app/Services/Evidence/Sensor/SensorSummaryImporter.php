<?php

namespace App\Services\Evidence\Sensor;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\EvidenceHasher;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Import a hash-verified aggregate sensor summary (ADR 0060, phase 3).
 *
 * The input is a normalised summary produced by a Suricata, Arkime or Hubble
 * exporter. It is aggregate only: counts, service edges and sensor health. Raw
 * events, flows, packet data, URLs and payloads are not accepted into the
 * stored payload.
 *
 * Without an externally supplied file SHA-256 the import is still possible but
 * is marked low confidence. A hash-verified snapshot is medium confidence; it
 * proves the bytes did not change after the exporter hashed them, not who
 * produced them.
 */
final class SensorSummaryImporter
{
    public const SCHEMA = 'opengrc.sensor-summary.v1';

    public const EVIDENCE_TYPE = 'sensor-summary';

    public function __construct(
        protected EvidenceIngestor $ingestor,
        protected EvidenceHasher $hasher,
    ) {}

    /**
     * @return array{
     *     source: string,
     *     sensor: string|null,
     *     window: array{from: string|null, to: string|null},
     *     events: int,
     *     created: int,
     *     duplicate: int,
     *     failed: int,
     *     hash_verified: bool,
     *     dry_run: bool,
     *     warnings: array<int, string>,
     *     errors: array<int, string>
     * }
     */
    public function import(
        string $json,
        string $sourceName,
        ?string $controlCode = null,
        ?string $assetIdentifier = null,
        ?string $expectedSha256 = null,
        bool $allowUnhashed = false,
        bool $dryRun = false,
    ): array {
        $report = [
            'source' => $sourceName,
            'sensor' => null,
            'window' => ['from' => null, 'to' => null],
            'events' => 0,
            'created' => 0,
            'duplicate' => 0,
            'failed' => 0,
            'hash_verified' => false,
            'dry_run' => $dryRun,
            'warnings' => [],
            'errors' => [],
        ];

        $fileHash = hash('sha256', $json);

        if ($expectedSha256 !== null) {
            if (preg_match('/^[a-f0-9]{64}$/', $expectedSha256) !== 1) {
                $report['failed'] = 1;
                $report['errors'][] = 'The expected SHA-256 must be 64 lowercase hex characters.';

                return $report;
            }

            if (! hash_equals($expectedSha256, $fileHash)) {
                $report['failed'] = 1;
                $report['errors'][] = 'The file SHA-256 does not match the expected value.';

                return $report;
            }

            $report['hash_verified'] = true;
        } elseif (! $allowUnhashed) {
            $report['failed'] = 1;
            $report['errors'][] = 'A file SHA-256 is required. Pass --sha256, or use --allow-unhashed to store the summary as low confidence.';

            return $report;
        } else {
            $report['warnings'][] = 'The summary is unhashed. It is stored with low confidence; authenticity is not established.';
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $report['failed'] = 1;
            $report['errors'][] = 'The summary is not valid JSON.';

            return $report;
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            $report['failed'] = 1;
            $report['errors'][] = 'The summary must be a JSON object.';

            return $report;
        }

        $summary = $this->normalise($decoded, $report['errors']);

        if ($summary === null) {
            $report['failed'] = 1;

            return $report;
        }

        $report['sensor'] = $summary['sensor'];
        $report['window'] = ['from' => $summary['window']['from'], 'to' => $summary['window']['to']];
        $report['events'] = $summary['counts']['events'] ?? 0;

        $seed = [
            'schema' => self::SCHEMA,
            'source' => $summary['source'],
            'sensor' => $summary['sensor'],
            'window' => $summary['window'],
            'counts' => $summary['counts'],
            'by_verdict' => $summary['by_verdict'],
            'by_protocol' => $summary['by_protocol'],
            'by_service' => $summary['by_service'],
            'edges' => $summary['edges'],
            'health' => $summary['health'],
        ];

        $externalId = 'sensor-summary:'.$summary['source'].':'.$summary['sensor'].':'.$this->hasher->hash($this->hasher->canonicalize($seed));
        $payload = [
            'source' => 'sensor-summary',
            'schema' => self::SCHEMA,
            'sensor_source' => $summary['source'],
            'sensor' => $summary['sensor'],
            'window' => $summary['window'],
            'counts' => $summary['counts'],
            'by_verdict' => $summary['by_verdict'],
            'by_protocol' => $summary['by_protocol'],
            'by_service' => $summary['by_service'],
            'edges' => $summary['edges'],
            'health' => $summary['health'],
            'verification' => [
                'file_sha256' => $report['hash_verified'] ? $fileHash : null,
                'hash_verified' => $report['hash_verified'],
                'confidence' => $report['hash_verified'] ? 'medium' : 'low',
            ],
            'received_at' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
        ];

        if ($dryRun) {
            $report['duplicate'] = $this->duplicateCount($sourceName, $externalId);
            $report['warnings'][] = 'Dry run: no source, job or evidence record was written.';

            return $report;
        }

        try {
            $source = $this->resolveSource($sourceName);
        } catch (RuntimeException $exception) {
            $report['failed'] = 1;
            $report['errors'][] = $exception->getMessage();

            return $report;
        }

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Running,
            'started_at' => now(),
            'records_seen' => 1,
        ]);

        try {
            $record = $this->ingestor->ingest(
                $source,
                $job,
                new NormalisedEvidence(
                    externalId: $externalId,
                    evidenceType: self::EVIDENCE_TYPE,
                    status: ControlStatus::Unknown,
                    collectedAt: $summary['collected_at'],
                    payload: $payload,
                    controlIdentifier: $controlCode,
                    assetIdentifier: $assetIdentifier,
                ),
            );
        } catch (Throwable $exception) {
            $job->forceFill(['records_failed' => 1])->save();
            $job->markFailed($exception->getMessage());

            $report['failed'] = 1;
            $report['errors'][] = $exception->getMessage();

            return $report;
        }

        if ($record->wasRecentlyCreated) {
            $report['created'] = 1;
            $job->forceFill(['records_created' => 1])->save();
        } else {
            $report['duplicate'] = 1;
            $job->forceFill(['records_duplicate' => 1])->save();
        }

        $job->markCompleted();
        $source->forceFill(['last_run_at' => now()])->save();

        return $report;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $errors
     * @return array{
     *     source: string,
     *     sensor: string,
     *     window: array{from: string, to: string},
     *     collected_at: CarbonImmutable,
     *     counts: array<string, int>,
     *     by_verdict: array<string, int>,
     *     by_protocol: array<string, int>,
     *     by_service: array<int, array{name: string, count: int}>,
     *     edges: array<int, array{from: string, to: string, count: int}>,
     *     health: array<string, mixed>
     * }|null
     */
    private function normalise(array $input, array &$errors): ?array
    {
        if (($input['schema'] ?? null) !== self::SCHEMA) {
            $errors[] = 'The schema is not '.self::SCHEMA.'.';

            return null;
        }

        $source = $this->string($input['source'] ?? null, 64);
        $sensor = $this->string($input['sensor'] ?? null, 128);
        $window = $input['window'] ?? null;

        if ($source === null || $sensor === null) {
            $errors[] = 'source and sensor are required.';

            return null;
        }

        if (! is_array($window)) {
            $errors[] = 'window must be an object with from and to.';

            return null;
        }

        $from = $this->timestamp($window['from'] ?? null);
        $to = $this->timestamp($window['to'] ?? null);

        if ($from === null || $to === null) {
            $errors[] = 'window.from and window.to must be ISO-8601 timestamps.';

            return null;
        }

        if ($to->lt($from)) {
            $errors[] = 'window.to must not precede window.from.';

            return null;
        }

        return [
            'source' => $source,
            'sensor' => $sensor,
            'window' => ['from' => $from->toIso8601ZuluString(), 'to' => $to->toIso8601ZuluString()],
            'collected_at' => $to,
            'counts' => $this->counts($input['counts'] ?? null),
            'by_verdict' => $this->counts($input['by_verdict'] ?? null),
            'by_protocol' => $this->counts($input['by_protocol'] ?? null),
            'by_service' => $this->namedCounts($input['by_service'] ?? null),
            'edges' => $this->edges($input['edges'] ?? null),
            'health' => $this->health($input['health'] ?? null),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function counts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $counts = [];

        foreach (array_slice($value, 0, 50, true) as $key => $count) {
            if (! is_string($key) || ! is_int($count) || $count < 0) {
                continue;
            }

            $counts[mb_substr($key, 0, 64)] = $count;
        }

        return $counts;
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function namedCounts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach (array_slice($value, 0, 200) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $this->string($item['name'] ?? null, 128);

            if ($name === null || ! is_int($item['count'] ?? null) || $item['count'] < 0) {
                continue;
            }

            $items[] = ['name' => $name, 'count' => $item['count']];
        }

        return $items;
    }

    /**
     * @return array<int, array{from: string, to: string, count: int}>
     */
    private function edges(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $edges = [];

        foreach (array_slice($value, 0, 200) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $from = $this->string($item['from'] ?? null, 128);
            $to = $this->string($item['to'] ?? null, 128);

            if ($from === null || $to === null || ! is_int($item['count'] ?? null) || $item['count'] < 0) {
                continue;
            }

            $edges[] = ['from' => $from, 'to' => $to, 'count' => $item['count']];
        }

        return $edges;
    }

    /**
     * @return array<string, mixed>
     */
    private function health(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $health = [];
        $lastEvent = $this->timestamp($value['last_event_at'] ?? null);

        if ($lastEvent !== null) {
            $health['last_event_at'] = $lastEvent->toIso8601ZuluString();
        }

        foreach (['capture_drops', 'uptime_seconds'] as $key) {
            if (is_int($value[$key] ?? null) && $value[$key] >= 0) {
                $health[$key] = $value[$key];
            }
        }

        if (is_bool($value['healthy'] ?? null)) {
            $health['healthy'] = $value['healthy'];
        }

        return $health;
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function string(mixed $value, int $cap): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, $cap) : null;
    }

    private function resolveSource(string $sourceName): EvidenceSource
    {
        $source = EvidenceSource::query()->where('name', $sourceName)->first();

        if ($source instanceof EvidenceSource) {
            if ($source->type !== EvidenceSourceType::Sensor) {
                throw new RuntimeException("The source {$sourceName} is not a sensor-summary source.");
            }

            return $source;
        }

        return EvidenceSource::create([
            'name' => $sourceName,
            'type' => EvidenceSourceType::Sensor,
            'enabled' => false,
            'owner_id' => null,
        ]);
    }

    private function duplicateCount(string $sourceName, string $externalId): int
    {
        $source = EvidenceSource::query()->where('name', $sourceName)->first();

        if (! $source instanceof EvidenceSource) {
            return 0;
        }

        return EvidenceRecord::query()
            ->where('source_id', $source->getKey())
            ->where('external_id', $externalId)
            ->count();
    }
}
