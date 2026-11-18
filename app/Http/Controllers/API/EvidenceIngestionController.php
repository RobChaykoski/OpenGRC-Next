<?php

namespace App\Http\Controllers\API;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Http\Controllers\Controller;
use App\Models\EvidenceJob;
use App\Models\EvidenceSource;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Accept a discovery evidence snapshot from OpenDLP-NG.
 *
 * The payload is verified against the supplied SHA-256 before it reaches the
 * append-only evidence ledger. No raw matched values are accepted.
 */
class EvidenceIngestionController extends Controller
{
    public function store(Request $request, EvidenceIngestor $ingestor): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:64'],
            'sha256' => ['required', 'string', 'size:64'],
            'evidence' => ['required', 'array'],
        ]);

        $canonical = $this->canonical($validated['evidence']);
        $digest = hash('sha256', $canonical);

        if (! hash_equals($validated['sha256'], $digest)) {
            throw ValidationException::withMessages([
                'sha256' => 'The payload hash does not match the evidence.',
            ]);
        }

        $source = EvidenceSource::firstOrCreate(
            ['name' => 'Discovery Engine'],
            ['type' => EvidenceSourceType::Other, 'enabled' => true],
        );

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Completed,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $record = $ingestor->ingest($source, $job, new NormalisedEvidence(
            externalId: $validated['id'],
            evidenceType: 'data-discovery',
            status: ControlStatus::Unknown,
            collectedAt: CarbonImmutable::now(),
            payload: $validated['evidence'],
        ));

        return response()->json([
            'evidenceId' => $record->getKey(),
            'status' => 'accepted',
        ], 201);
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
