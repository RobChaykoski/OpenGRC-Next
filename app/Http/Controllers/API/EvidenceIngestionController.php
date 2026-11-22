<?php

namespace App\Http\Controllers\API;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Http\Controllers\Controller;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Accept an external, hash-verified evidence snapshot.
 *
 * Producers send discovery, detection-coverage or other approved evidence
 * types. The payload is verified against the supplied SHA-256 before it reaches
 * the append-only evidence ledger. Tokens are scoped (evidence.write) and bound
 * to a client, so an external_id is unique per client and a replay returns the
 * original record instead of writing a second one.
 *
 * The record status is always Unknown: an external producer may describe what
 * it observed, but only a validation rule decides whether a control passed.
 */
class EvidenceIngestionController extends Controller
{
    /**
     * Evidence types an external producer may submit. Keep this list explicit:
     * a free-text type would let a producer invent a control vocabulary.
     */
    public const EVIDENCE_TYPES = [
        'data-discovery',
        'detection-coverage',
        'adversary-emulation',
        'security-testing',
        'awareness-training',
    ];

    public function store(Request $request, EvidenceIngestor $ingestor): JsonResponse
    {
        $clientId = (string) $request->user()->currentAccessToken()->client_id;

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:191'],
            'sha256' => ['required', 'string', 'size:64'],
            'evidence' => ['required', 'array'],
            'evidenceType' => ['nullable', 'string', Rule::in(self::EVIDENCE_TYPES)],
            'capturedAt' => ['nullable', 'date'],
            'assetIdentifier' => ['nullable', 'string', 'max:255'],
            'controlIdentifier' => ['nullable', 'string', 'max:255'],
        ]);

        $evidenceType = $validated['evidenceType'] ?? 'data-discovery';

        $canonical = $this->canonical($validated['evidence']);
        $digest = hash('sha256', $canonical);

        if (! hash_equals($validated['sha256'], $digest)) {
            throw ValidationException::withMessages([
                'sha256' => 'The payload hash does not match the evidence.',
            ]);
        }

        $existing = EvidenceRecord::query()
            ->where('client_id', $clientId)
            ->where('external_id', $validated['id'])
            ->first();

        if ($existing instanceof EvidenceRecord) {
            return $this->accepted($existing, 200);
        }

        $source = EvidenceSource::firstOrCreate(
            ['name' => $this->sourceName($clientId, $evidenceType)],
            ['type' => EvidenceSourceType::Other, 'enabled' => true],
        );

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Completed,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $collectedAt = isset($validated['capturedAt'])
            ? CarbonImmutable::parse($validated['capturedAt'])
            : CarbonImmutable::now();

        try {
            $record = $ingestor->ingest($source, $job, new NormalisedEvidence(
                externalId: $validated['id'],
                evidenceType: $evidenceType,
                status: ControlStatus::Unknown,
                collectedAt: $collectedAt,
                payload: $validated['evidence'],
                clientId: $clientId,
                assetIdentifier: $validated['assetIdentifier'] ?? null,
                controlIdentifier: $validated['controlIdentifier'] ?? null,
            ));
        } catch (QueryException $exception) {
            // Two dispatcher retries can race. The unique index is the
            // authority; return the row the winner wrote.
            $record = EvidenceRecord::query()
                ->where('client_id', $clientId)
                ->where('external_id', $validated['id'])
                ->first();

            if (! $record instanceof EvidenceRecord) {
                throw $exception;
            }

            return $this->accepted($record, 200);
        }

        return $this->accepted($record, 201);
    }

    private function sourceName(string $clientId, string $evidenceType): string
    {
        return match ($evidenceType) {
            'data-discovery' => "Discovery: {$clientId}",
            'detection-coverage' => "Detection: {$clientId}",
            'adversary-emulation' => "Range: {$clientId}",
            'security-testing' => "AppSec: {$clientId}",
            default => "External: {$clientId}",
        };
    }

    private function accepted(EvidenceRecord $record, int $status): JsonResponse
    {
        return response()->json([
            'evidenceId' => $record->getKey(),
            'status' => 'accepted',
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonical(array $payload): string
    {
        $this->sortKeys($payload);

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sortKeys(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sortKeys($value);
            }
        }
    }
}
