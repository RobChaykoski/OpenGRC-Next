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
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Verify and import Prismor signed telemetry receipts as control evidence
 * (ADR 0060).
 *
 * The batch is fail-closed: if any receipt fails verification, or the chain has
 * a broken link, nothing is written. A duplicate is not a failure. The stored
 * payload is a whitelist of receipt metadata; tool inputs and content are never
 * copied.
 */
final class PrismorReceiptImporter
{
    public const EVIDENCE_TYPE = 'agent-action-receipt';

    public function __construct(
        protected PrismorReceiptVerifier $verifier,
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
     *     verdicts: array<string, int>,
     *     severities: array<string, int>,
     *     devices: array<int, string>,
     *     window: array{from: string|null, to: string|null},
     *     warnings: array<int, string>,
     *     errors: array<int, string>
     * }
     */
    public function import(
        array $receipts,
        string $sourceName,
        ?string $controlCode = null,
        ?string $assetIdentifier = null,
        ?string $pinnedPublicKey = null,
        bool $allowUnpinned = false,
        bool $dryRun = false,
    ): array {
        $report = [
            'source' => $sourceName,
            'receipts' => count($receipts),
            'verified' => 0,
            'created' => 0,
            'duplicate' => 0,
            'failed' => 0,
            'pinned' => $pinnedPublicKey !== null,
            'dry_run' => $dryRun,
            'verdicts' => [],
            'severities' => [],
            'devices' => [],
            'window' => ['from' => null, 'to' => null],
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

        if ($pinnedPublicKey === null && ! $allowUnpinned) {
            $report['failed'] = count($receipts);
            $report['errors'][] = 'A pinned public key is required. Pass the base64 Ed25519 key, or use --allow-unpinned to accept the inline key with low confidence.';

            return $report;
        }

        if ($pinnedPublicKey !== null && $this->decodedKey($pinnedPublicKey) === null) {
            $report['failed'] = count($receipts);
            $report['errors'][] = 'The pinned public key is not a 32-byte Ed25519 key in base64.';

            return $report;
        }

        /** @var array<int, array<string, mixed>> $verified */
        $verified = [];
        $from = null;
        $to = null;

        foreach ($receipts as $index => $receipt) {
            $verification = $this->verifier->verify($receipt, $pinnedPublicKey);

            foreach ($verification['warnings'] as $warning) {
                $report['warnings'][] = 'Receipt '.($index + 1).': '.$warning;
            }

            if (! $verification['valid'] || $verification['receipt'] === null) {
                $report['failed']++;

                foreach ($verification['errors'] as $error) {
                    $report['errors'][] = 'Receipt '.($index + 1).': '.$error;
                }

                continue;
            }

            $normalized = $verification['receipt'];
            $report['verified']++;
            $report['verdicts'][(string) $normalized['verdict']] = ($report['verdicts'][(string) $normalized['verdict']] ?? 0) + 1;
            $report['severities'][(string) $normalized['severity']] = ($report['severities'][(string) $normalized['severity']] ?? 0) + 1;

            if (! in_array((string) $normalized['device_id'], $report['devices'], true)) {
                $report['devices'][] = (string) $normalized['device_id'];
            }

            $collectedAt = $normalized['ts_at'];

            if ($collectedAt instanceof CarbonImmutable) {
                $from = $from === null || $collectedAt->lt($from) ? $collectedAt : $from;
                $to = $to === null || $collectedAt->gt($to) ? $collectedAt : $to;
            }

            $verified[] = $normalized;
        }

        if ($from !== null && $to !== null) {
            $report['window'] = [
                'from' => $from->toIso8601ZuluString(),
                'to' => $to->toIso8601ZuluString(),
            ];
        }

        if ($report['failed'] > 0) {
            $report['warnings'][] = 'The batch was not imported. Correct the receipt errors and run it again.';

            return $report;
        }

        $chain = $this->inspectChain($verified);
        $identityErrors = $this->inspectIdentities($verified);
        $report['warnings'] = array_merge($report['warnings'], $chain['warnings']);
        $report['errors'] = array_merge($report['errors'], $chain['errors'], $identityErrors);

        if ($chain['errors'] !== [] || $identityErrors !== []) {
            $report['failed'] = count($receipts);

            return $report;
        }

        if ($dryRun) {
            $report['duplicate'] = $this->countDuplicates($sourceName, $verified);
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

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Running,
            'started_at' => now(),
            'records_seen' => count($receipts),
        ]);

        $conflicts = $this->findConflicts($source, $verified);

        if ($conflicts !== []) {
            $job->forceFill(['records_failed' => count($receipts)])->save();
            $job->markFailed(implode(' ', array_slice($conflicts, 0, 5)));
            $report['failed'] = count($receipts);
            $report['errors'] = array_merge($report['errors'], $conflicts);

            return $report;
        }

        try {
            DB::transaction(function () use ($source, $job, $verified, $controlCode, $assetIdentifier, &$report): void {
                foreach ($verified as $receipt) {
                    $record = $this->ingestor->ingest(
                        $source,
                        $job,
                        new NormalisedEvidence(
                            externalId: $this->externalId($receipt),
                            evidenceType: self::EVIDENCE_TYPE,
                            status: ControlStatus::Unknown,
                            collectedAt: $receipt['ts_at'],
                            payload: $this->payload($receipt, $report['pinned']),
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
            });
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
            'records_failed' => 0,
        ])->save();
        $job->markCompleted();

        $source->forceFill(['last_run_at' => now()])->save();

        return $report;
    }

    /**
     * @param  array<int, array<string, mixed>>  $receipts
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    private function inspectChain(array $receipts): array
    {
        $errors = [];
        $warnings = [];
        $byDevice = [];

        foreach ($receipts as $receipt) {
            $byDevice[(string) $receipt['device_id']][] = $receipt;
        }

        foreach ($byDevice as $device => $list) {
            usort($list, fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
            $seen = [];
            $previous = null;

            foreach ($list as $receipt) {
                $seq = (int) $receipt['seq'];

                if (isset($seen[$seq])) {
                    $errors[] = "Device {$device} has more than one receipt at seq {$seq}.";

                    continue;
                }

                $seen[$seq] = true;

                if ($previous !== null) {
                    $gap = $seq - (int) $previous['seq'];

                    if ($gap === 1 && $receipt['prev_hash'] !== $previous['hash']) {
                        $errors[] = "Device {$device} has a broken chain link between seq {$previous['seq']} and {$seq}.";
                    } elseif ($gap > 1) {
                        $warnings[] = "Device {$device} has a sequence gap between seq {$previous['seq']} and {$seq}.";
                    }
                } elseif ($seq > 0) {
                    $warnings[] = "Device {$device} starts at seq {$seq}; earlier receipts are not in this file.";
                }

                $previous = $receipt;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<int, array<string, mixed>>  $receipts
     * @return array<int, string>
     */
    private function inspectIdentities(array $receipts): array
    {
        $errors = [];
        $seen = [];

        foreach ($receipts as $receipt) {
            $externalId = $this->externalId($receipt);

            if (isset($seen[$externalId])) {
                $errors[] = "The receipt identity {$externalId} appears more than once in this batch.";
            }

            $seen[$externalId] = true;
        }

        return $errors;
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

    /**
     * @param  array<int, array<string, mixed>>  $receipts
     * @return array<int, string>
     */
    private function findConflicts(EvidenceSource $source, array $receipts): array
    {
        $errors = [];
        $externalIds = array_map(fn (array $receipt): string => $this->externalId($receipt), $receipts);

        $existing = EvidenceRecord::query()
            ->where('source_id', $source->getKey())
            ->whereIn('external_id', $externalIds)
            ->get()
            ->keyBy('external_id');

        foreach ($receipts as $receipt) {
            $externalId = $this->externalId($receipt);
            $record = $existing->get($externalId);

            if (! $record instanceof EvidenceRecord) {
                continue;
            }

            $storedHash = is_array($record->payload)
                ? ($record->payload['receipt']['hash'] ?? null)
                : null;
            $storedSignature = is_array($record->payload)
                ? ($record->payload['receipt']['signature'] ?? null)
                : null;

            if ($storedHash !== $receipt['hash'] || $storedSignature !== $receipt['signature']) {
                $errors[] = "The receipt {$externalId} already exists with different bytes. Reject the re-signed record and investigate.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $receipts
     */
    private function countDuplicates(string $sourceName, array $receipts): int
    {
        $source = EvidenceSource::query()->where('name', $sourceName)->first();

        if (! $source instanceof EvidenceSource) {
            return 0;
        }

        return EvidenceRecord::query()
            ->where('source_id', $source->getKey())
            ->whereIn('external_id', array_map(fn (array $receipt): string => $this->externalId($receipt), $receipts))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $receipt
     */
    private function externalId(array $receipt): string
    {
        return 'prismor:'.$receipt['device_id'].':'.$receipt['event_id'];
    }

    /**
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    private function payload(array $receipt, bool $pinned): array
    {
        return [
            'source' => 'prismor',
            'schema_version' => 1,
            'receipt' => [
                'event_id' => $receipt['event_id'],
                'verdict' => $receipt['verdict'],
                'severity' => $receipt['severity'],
                'rule_id' => $receipt['rule_id'],
                'tool_name' => $receipt['tool_name'],
                'evidence_hash' => $receipt['evidence_hash'],
                'session_id' => $receipt['session_id'],
                'ts' => $receipt['ts'],
                'device_id' => $receipt['device_id'],
                'agent' => $receipt['agent'],
                'agent_name' => $receipt['agent_name'],
                'subagent_id' => $receipt['subagent_id'],
                'subject' => $receipt['subject'],
                'seq' => $receipt['seq'],
                'prev_hash' => $receipt['prev_hash'],
                'hash' => $receipt['hash'],
                'signature' => $receipt['signature'],
                'signing_alg' => 'ed25519',
                'signing_pubkey' => $receipt['signing_pubkey'],
                'signing_key_id' => $receipt['signing_key_id'],
            ],
            'verification' => [
                'integrity' => true,
                'signature' => true,
                'pinned' => $pinned,
                'confidence' => $pinned ? 'high' : 'low',
                'key_id' => $receipt['signing_key_id'],
            ],
            'received_at' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
        ];
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
