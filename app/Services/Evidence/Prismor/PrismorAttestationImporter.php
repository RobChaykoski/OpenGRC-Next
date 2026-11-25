<?php

namespace App\Services\Evidence\Prismor;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Verify and import a Prismor signed attestation bundle (ADR 0060).
 *
 * The bundle is one signed snapshot of a device's governed-agent inventory,
 * discovery summary, posture findings, framework-coverage claim and trail
 * checkpoint. Only the whitelisted metadata is stored: agent names and modes,
 * aggregate discovery counts, coverage IDs/titles, severity/category counts and
 * the trail head. Audit messages, raw MCP URLs, provider-key locations and the
 * bundle body are not copied.
 */
final class PrismorAttestationImporter
{
    public const EVIDENCE_TYPE = 'agent-runtime-attestation';

    public function __construct(
        protected PrismorAttestationVerifier $verifier,
        protected EvidenceIngestor $ingestor,
    ) {}

    /**
     * @return array{
     *     source: string,
     *     schema: string|null,
     *     device_id: string|null,
     *     generated_at: string|null,
     *     agents: int,
     *     frameworks: int,
     *     created: int,
     *     duplicate: int,
     *     failed: int,
     *     pinned: bool,
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
        ?string $pinnedPublicKey = null,
        bool $allowUnpinned = false,
        bool $dryRun = false,
    ): array {
        $report = [
            'source' => $sourceName,
            'schema' => null,
            'device_id' => null,
            'generated_at' => null,
            'agents' => 0,
            'frameworks' => 0,
            'created' => 0,
            'duplicate' => 0,
            'failed' => 0,
            'pinned' => $pinnedPublicKey !== null,
            'dry_run' => $dryRun,
            'warnings' => [],
            'errors' => [],
        ];

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            $report['failed'] = 1;
            $report['errors'][] = 'The PHP sodium extension is required to verify Ed25519 attestations.';

            return $report;
        }

        if ($pinnedPublicKey === null && ! $allowUnpinned) {
            $report['failed'] = 1;
            $report['errors'][] = 'A pinned public key is required. Pass the base64 Ed25519 key, or use --allow-unpinned to accept the inline key with low confidence.';

            return $report;
        }

        if ($pinnedPublicKey !== null && $this->decodedKey($pinnedPublicKey) === null) {
            $report['failed'] = 1;
            $report['errors'][] = 'The pinned public key is not a 32-byte Ed25519 key in base64.';

            return $report;
        }

        $verification = $this->verifier->verify($json, $pinnedPublicKey);
        $report['warnings'] = $verification['warnings'];

        if (! $verification['valid'] || $verification['bundle'] === null) {
            $report['failed'] = 1;
            $report['errors'] = $verification['errors'];

            return $report;
        }

        $bundle = $verification['bundle'];
        $schema = is_string($bundle['schema'] ?? null) ? $bundle['schema'] : null;
        $deviceId = is_string($bundle['device_id'] ?? null) ? trim($bundle['device_id']) : '';
        $generatedAt = is_string($bundle['generated_at'] ?? null) ? $bundle['generated_at'] : null;
        $contentHash = is_string($bundle['content_hash'] ?? null) ? $bundle['content_hash'] : null;

        $report['schema'] = $schema;
        $report['device_id'] = $deviceId;
        $report['generated_at'] = $generatedAt;

        if ($deviceId === '' || $generatedAt === null || $contentHash === null) {
            $report['failed'] = 1;
            $report['errors'][] = 'The bundle is missing device_id, generated_at or content_hash.';

            return $report;
        }

        try {
            $collectedAt = CarbonImmutable::parse($generatedAt)->utc();
        } catch (Throwable) {
            $report['failed'] = 1;
            $report['errors'][] = 'The bundle generated_at is not a valid timestamp.';

            return $report;
        }

        $agents = $this->agents($bundle['agents'] ?? null);
        $coverage = $this->coverage($bundle['framework_coverage'] ?? null);
        $report['agents'] = count($agents);
        $report['frameworks'] = count($coverage['frameworks']);

        $payload = [
            'source' => 'prismor',
            'schema' => $schema,
            'attestation' => [
                'device_id' => $deviceId,
                'generated_at' => $generatedAt,
                'prismor_version' => is_string($bundle['prismor_version'] ?? null) ? $bundle['prismor_version'] : null,
                'content_hash' => $contentHash,
                'signature' => is_string($bundle['signature'] ?? null) ? $bundle['signature'] : null,
                'signing_alg' => is_string($bundle['signing_alg'] ?? null) ? $bundle['signing_alg'] : null,
                'signing_pubkey' => is_string($bundle['signing_pubkey'] ?? null) ? $bundle['signing_pubkey'] : null,
                'signing_key_id' => is_string($bundle['signing_key_id'] ?? null) ? $bundle['signing_key_id'] : null,
                'agents' => $agents,
                'discovery_summary' => $this->summary($bundle['discovery'] ?? null, [
                    'governed', 'shadow', 'coverage_percent', 'agents', 'mcp', 'keys', 'total',
                ]),
                'audit_findings' => $this->findingSummary($bundle['audit_findings'] ?? null),
                'framework_coverage' => $coverage,
                'trail_checkpoint' => $this->checkpoint($bundle['trail_checkpoint'] ?? null),
            ],
            'verification' => [
                'content_hash' => true,
                'signature' => true,
                'pinned' => $verification['pinned'],
                'confidence' => $verification['pinned'] ? 'high' : 'low',
                'key_id' => $verification['key_id'],
            ],
            'received_at' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
        ];

        $externalId = 'prismor-attestation:'.$deviceId.':'.$contentHash;

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

        $conflict = $this->conflict($source, $externalId, (string) $contentHash, $bundle['signature'] ?? null);

        if ($conflict !== null) {
            $report['failed'] = 1;
            $report['errors'][] = $conflict;

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
                    collectedAt: $collectedAt,
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
     * @return array<int, array<string, mixed>>
     */
    private function agents(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $agents = [];

        foreach (array_slice($value, 0, 200) as $agent) {
            if (! is_array($agent)) {
                continue;
            }

            $agents[] = [
                'name' => $this->string($agent['name'] ?? null, 128),
                'framework' => $this->string($agent['framework'] ?? null, 64),
                'enabled' => is_bool($agent['enabled'] ?? null) ? $agent['enabled'] : null,
                'mode' => $this->string($agent['mode'] ?? null, 32),
                'last_seen' => $this->string($agent['last_seen'] ?? null, 64),
            ];
        }

        return $agents;
    }

    /**
     * @param  array<int, string>  $allowedKeys
     * @return array<string, mixed>
     */
    private function summary(mixed $value, array $allowedKeys): array
    {
        if (! is_array($value)) {
            return [];
        }

        $summary = $value['summary'] ?? null;

        if (! is_array($summary)) {
            return [];
        }

        $result = [];

        foreach ($allowedKeys as $key) {
            $item = $summary[$key] ?? null;

            if (is_string($item)) {
                $result[$key] = mb_substr($item, 0, 64);
            } elseif (is_int($item) || is_float($item) || is_bool($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array{total: int, by_severity: array<string, int>, by_category: array<string, int>}
     */
    private function findingSummary(mixed $value): array
    {
        $summary = ['total' => 0, 'by_severity' => [], 'by_category' => []];

        if (! is_array($value)) {
            return $summary;
        }

        foreach ($value as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $summary['total']++;
            $severity = $this->string($finding['severity'] ?? null, 32) ?? 'unknown';
            $category = $this->string($finding['category'] ?? null, 64) ?? 'unknown';
            $summary['by_severity'][$severity] = ($summary['by_severity'][$severity] ?? 0) + 1;
            $summary['by_category'][$category] = ($summary['by_category'][$category] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @return array{frameworks: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function coverage(mixed $value): array
    {
        if (! is_array($value)) {
            return ['frameworks' => [], 'summary' => []];
        }

        $frameworks = [];

        foreach (array_slice(is_array($value['frameworks'] ?? null) ? $value['frameworks'] : [], 0, 50) as $framework) {
            if (! is_array($framework)) {
                continue;
            }

            $controls = [];

            foreach (array_slice(is_array($framework['controls'] ?? null) ? $framework['controls'] : [], 0, 200) as $control) {
                if (! is_array($control)) {
                    continue;
                }

                $controls[] = [
                    'id' => $this->string($control['id'] ?? null, 64),
                    'covered' => is_bool($control['covered'] ?? null) ? $control['covered'] : null,
                ];
            }

            $frameworks[] = [
                'id' => $this->string($framework['id'] ?? null, 64),
                'name' => $this->string($framework['name'] ?? null, 200),
                'covered' => is_int($framework['covered'] ?? null) ? $framework['covered'] : null,
                'total' => is_int($framework['total'] ?? null) ? $framework['total'] : null,
                'controls' => $controls,
            ];
        }

        $summary = [];
        $coverageSummary = is_array($value['summary'] ?? null) ? $value['summary'] : [];

        foreach (['frameworks', 'covered', 'total'] as $key) {
            $item = $coverageSummary[$key] ?? null;

            if (is_int($item) || is_float($item) || is_bool($item)) {
                $summary[$key] = $item;
            }
        }

        return ['frameworks' => $frameworks, 'summary' => $summary];
    }

    /**
     * @return array{seq: int|null, hash: string|null}
     */
    private function checkpoint(mixed $value): array
    {
        if (! is_array($value)) {
            return ['seq' => null, 'hash' => null];
        }

        return [
            'seq' => is_int($value['seq'] ?? null) ? $value['seq'] : null,
            'hash' => $this->string($value['hash'] ?? null, 64),
        ];
    }

    private function string(mixed $value, int $cap): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, $cap) : null;
    }

    private function resolveSource(string $sourceName): EvidenceSource
    {
        $source = EvidenceSource::query()->where('name', $sourceName)->first();

        if ($source instanceof EvidenceSource) {
            if ($source->type !== EvidenceSourceType::Prismor) {
                throw new RuntimeException("The source {$sourceName} is not a Prismor source.");
            }

            return $source;
        }

        return EvidenceSource::create([
            'name' => $sourceName,
            'type' => EvidenceSourceType::Prismor,
            'enabled' => false,
            'owner_id' => null,
        ]);
    }

    private function conflict(EvidenceSource $source, string $externalId, string $contentHash, mixed $signature): ?string
    {
        $record = EvidenceRecord::query()
            ->where('source_id', $source->getKey())
            ->where('external_id', $externalId)
            ->first();

        if (! $record instanceof EvidenceRecord) {
            return null;
        }

        $storedHash = is_array($record->payload) ? ($record->payload['attestation']['content_hash'] ?? null) : null;
        $storedSignature = is_array($record->payload) ? ($record->payload['attestation']['signature'] ?? null) : null;

        if ($storedHash !== $contentHash || $storedSignature !== $signature) {
            return "The attestation {$externalId} already exists with different bytes. Reject the re-signed bundle and investigate.";
        }

        return null;
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

    private function decodedKey(string $key): ?string
    {
        $decoded = base64_decode($key, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        return $decoded;
    }
}
