<?php

namespace App\Services\Evidence\Pipelock;

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
 * Verify and import Pipelock ActionReceipt v1 records (ADR 0060).
 *
 * Signatures are verified per receipt, then the batch is replayed as a chain:
 * chain_seq must increment by one and chain_prev_hash must match the previous
 * receipt's canonical envelope hash. A batch that starts at genesis and links
 * completely is recorded with `chain_verified: true`; a mid-chain slice is
 * refused unless the operator opts in, and is then recorded as unverified
 * because its ancestry cannot be shown. The target is stored as a host plus a
 * SHA-256 of the full target, never the raw URL with its path or query values.
 * Prompts, tool arguments, request bodies and payloads are never copied.
 */
final class PipelockReceiptImporter
{
    public const EVIDENCE_TYPE = 'agent-action-receipt';

    public function __construct(
        protected PipelockReceiptVerifier $verifier,
        protected PipelockChainVerifier $chain,
        protected EvidenceIngestor $ingestor,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $receipts
     * @return array{
     *     source: string,
     *     receipts: int,
     *     verified: int,
     *     created: int,
     *     duplicate: int,
     *     failed: int,
     *     pinned: bool,
     *     dry_run: bool,
     *     chain_verified: bool,
     *     chain_scope: 'genesis'|'partial'|'none',
     *     chain_links: int,
     *     chain_final_seq: int|null,
     *     chain_head: string|null,
     *     verdicts: array<string, int>,
     *     action_types: array<string, int>,
     *     warnings: array<int, string>,
     *     errors: array<int, string>
     * }
     */
    public function import(
        array $receipts,
        string $sourceName,
        ?string $controlCode = null,
        ?string $assetIdentifier = null,
        ?string $pinnedSignerKey = null,
        bool $allowUnpinned = false,
        bool $dryRun = false,
        bool $allowPartialChain = false,
    ): array {
        $report = [
            'source' => $sourceName,
            'receipts' => count($receipts),
            'verified' => 0,
            'created' => 0,
            'duplicate' => 0,
            'failed' => 0,
            'pinned' => $pinnedSignerKey !== null,
            'dry_run' => $dryRun,
            'chain_verified' => false,
            'chain_scope' => 'none',
            'chain_links' => 0,
            'chain_final_seq' => null,
            'chain_head' => null,
            'verdicts' => [],
            'action_types' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if ($receipts === []) {
            $report['errors'][] = 'No receipts were supplied.';

            return $report;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            $report['failed'] = count($receipts);
            $report['errors'][] = 'The PHP sodium extension is required to verify Ed25519 receipts.';

            return $report;
        }

        if ($pinnedSignerKey === null && ! $allowUnpinned) {
            $report['failed'] = count($receipts);
            $report['errors'][] = 'A pinned signer key is required. Pass the 64-hex Ed25519 key, or use --allow-unpinned to accept the inline key with low confidence.';

            return $report;
        }

        /** @var array<int, array<string, mixed>> $verified */
        $verified = [];
        /** @var array<int, array<string, mixed>> $envelopes */
        $envelopes = [];
        $identities = [];

        foreach ($receipts as $index => $receipt) {
            $json = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $verification = $this->verifier->verify($json, $pinnedSignerKey);

            $report['warnings'] = array_merge($report['warnings'], $verification['warnings']);

            if (! $verification['valid'] || $verification['receipt'] === null) {
                $report['failed']++;

                foreach ($verification['errors'] as $error) {
                    $report['errors'][] = 'Receipt '.($index + 1).': '.$error;
                }

                continue;
            }

            $action = $verification['receipt'];
            $actionId = (string) $action['action_id'];

            if (isset($identities[$actionId])) {
                $report['failed'] += count($receipts);
                $report['errors'][] = "The action id {$actionId} appears more than once in this batch.";

                return $report;
            }

            $identities[$actionId] = true;
            $report['verified']++;
            $report['verdicts'][(string) $action['verdict']] = ($report['verdicts'][(string) $action['verdict']] ?? 0) + 1;
            $report['action_types'][(string) $action['action_type']] = ($report['action_types'][(string) $action['action_type']] ?? 0) + 1;

            $action['_verification'] = $verification;
            $action['_envelope_signature'] = $receipt['signature'] ?? null;
            $verified[] = $action;
            $envelopes[] = $receipt;
        }

        if ($report['failed'] > 0) {
            $report['warnings'][] = 'The batch was not imported. Correct the receipt errors and run it again.';

            return $report;
        }

        $chain = $this->chain->verify($envelopes, $allowPartialChain);

        $report['warnings'] = array_merge($report['warnings'], $chain['warnings']);
        $report['errors'] = array_merge($report['errors'], $chain['errors']);
        $report['chain_verified'] = $chain['complete'];
        $report['chain_scope'] = $chain['scope'];
        $report['chain_links'] = $chain['links'];
        $report['chain_final_seq'] = $chain['final_seq'];
        $report['chain_head'] = $chain['head_hash'];

        if ($chain['errors'] !== []) {
            $report['failed'] = count($receipts);
            $report['warnings'][] = 'The batch was not imported. Correct the receipt errors and run it again.';

            return $report;
        }

        foreach ($verified as $index => $action) {
            $verified[$index]['_chain_verified'] = $chain['complete'];
            $verified[$index]['_chain_scope'] = $chain['scope'];
            $verified[$index]['_chain_hash'] = $chain['hashes'][$index] ?? null;
        }

        if ($dryRun) {
            $report['warnings'][] = 'Dry run: no source, job or evidence record was written.';

            return $report;
        }

        try {
            $source = $this->resolveSource($sourceName);
        } catch (RuntimeException $exception) {
            $report['failed'] = count($receipts);
            $report['errors'][] = $exception->getMessage();

            return $report;
        }

        $conflicts = [];

        foreach ($verified as $action) {
            $conflict = $this->conflict($source, $action);

            if ($conflict !== null) {
                $conflicts[] = $conflict;
            }
        }

        if ($conflicts !== []) {
            $report['failed'] = count($receipts);
            $report['errors'] = $conflicts;

            return $report;
        }

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Running,
            'started_at' => now(),
            'records_seen' => count($receipts),
        ]);

        try {
            foreach ($verified as $action) {
                $record = $this->ingestor->ingest(
                    $source,
                    $job,
                    new NormalisedEvidence(
                        externalId: 'pipelock:'.$action['action_id'],
                        evidenceType: self::EVIDENCE_TYPE,
                        status: ControlStatus::Unknown,
                        collectedAt: CarbonImmutable::parse((string) $action['timestamp'])->utc(),
                        payload: $this->payload($action),
                        controlIdentifier: $controlCode,
                        assetIdentifier: $assetIdentifier,
                    ),
                );

                if ($record->wasRecentlyCreated) {
                    $report['created']++;
                } else {
                    $report['duplicate']++;
                }
            }
        } catch (Throwable $exception) {
            $job->forceFill(['records_failed' => count($receipts)])->save();
            $job->markFailed($exception->getMessage());

            $report['failed'] = count($receipts);
            $report['errors'][] = $exception->getMessage();

            return $report;
        }

        $job->forceFill([
            'records_created' => $report['created'],
            'records_duplicate' => $report['duplicate'],
        ])->save();
        $job->markCompleted();
        $source->forceFill(['last_run_at' => now()])->save();

        return $report;
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function payload(array $action): array
    {
        $verification = $action['_verification'];
        $target = (string) $action['target'];

        return [
            'source' => 'pipelock',
            'schema' => 'pipelock.action-receipt.v1',
            'receipt' => [
                'action_id' => $action['action_id'],
                'action_type' => $action['action_type'],
                'timestamp' => $action['timestamp'],
                'principal' => $this->string($action['principal'] ?? null, 128),
                'actor' => $this->string($action['actor'] ?? null, 128),
                'verdict' => $this->string($action['verdict'] ?? null, 64),
                'transport' => $this->string($action['transport'] ?? null, 64),
                'method' => $this->string($action['method'] ?? null, 32),
                'policy_hash' => $this->string($action['policy_hash'] ?? null, 128),
                'side_effect_class' => $this->string($action['side_effect_class'] ?? null, 32),
                'reversibility' => $this->string($action['reversibility'] ?? null, 32),
                'data_classes_in' => $this->stringList($action['data_classes_in'] ?? null),
                'data_classes_out' => $this->stringList($action['data_classes_out'] ?? null),
                'decision_phase' => $this->string($action['decision_phase'] ?? null, 32),
                'layer' => $this->string($action['layer'] ?? null, 64),
                'pattern' => $this->string($action['pattern'] ?? null, 128),
                'severity' => $this->string($action['severity'] ?? null, 32),
                'session_id' => $this->string($action['session_id'] ?? null, 128),
                'request_id' => $this->string($action['request_id'] ?? null, 128),
                'target_host' => $this->targetHost($target),
                'target_sha256' => hash('sha256', $target),
                'chain_prev_hash' => $this->string($action['chain_prev_hash'] ?? null, 128),
                'chain_seq' => is_int($action['chain_seq'] ?? null) ? $action['chain_seq'] : null,
                'chain_hash' => is_string($action['_chain_hash'] ?? null) ? $action['_chain_hash'] : null,
                'signature' => is_string($action['_envelope_signature'] ?? null) ? $action['_envelope_signature'] : null,
                'signer_key' => $verification['signer_key'],
            ],
            'verification' => [
                'signature' => true,
                'pinned' => $verification['pinned'],
                'confidence' => $verification['pinned'] ? 'high' : 'low',
                'canonical_sha256' => $verification['canonical_sha256'],
                'chain_verified' => (bool) ($action['_chain_verified'] ?? false),
                'chain_scope' => is_string($action['_chain_scope'] ?? null) ? $action['_chain_scope'] : 'none',
            ],
            'received_at' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
        ];
    }

    private function targetHost(string $target): ?string
    {
        $parts = parse_url($target);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $host = strtolower($parts['scheme']).'://'.strtolower($parts['host']);

        if (isset($parts['port'])) {
            $host .= ':'.$parts['port'];
        }

        return mb_substr($host, 0, 255);
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $items = [];

        foreach (array_slice($value, 0, 20) as $item) {
            if (is_string($item) && $item !== '') {
                $items[] = mb_substr($item, 0, 128);
            }
        }

        return $items;
    }

    private function string(mixed $value, int $cap): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, $cap) : null;
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function conflict(EvidenceSource $source, array $action): ?string
    {
        $externalId = 'pipelock:'.$action['action_id'];
        $record = EvidenceRecord::query()
            ->where('source_id', $source->getKey())
            ->where('external_id', $externalId)
            ->first();

        if (! $record instanceof EvidenceRecord) {
            return null;
        }

        $storedSignature = is_array($record->payload) ? ($record->payload['receipt']['signature'] ?? null) : null;
        $storedTimestamp = is_array($record->payload) ? ($record->payload['receipt']['timestamp'] ?? null) : null;

        if ($storedSignature !== ($action['_envelope_signature'] ?? null) || $storedTimestamp !== $action['timestamp']) {
            return "The action {$externalId} already exists with different bytes. Reject the re-signed receipt and investigate.";
        }

        return null;
    }

    private function resolveSource(string $sourceName): EvidenceSource
    {
        $source = EvidenceSource::query()->where('name', $sourceName)->first();

        if ($source instanceof EvidenceSource) {
            if ($source->type !== EvidenceSourceType::Pipelock) {
                throw new RuntimeException("The source {$sourceName} is not a Pipelock source.");
            }

            return $source;
        }

        return EvidenceSource::create([
            'name' => $sourceName,
            'type' => EvidenceSourceType::Pipelock,
            'enabled' => false,
            'owner_id' => null,
        ]);
    }
}
