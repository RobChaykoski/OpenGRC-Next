<?php

namespace App\Services\Evidence;

use App\Models\Asset;
use App\Models\Control;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Support\Evidence\NormalisedEvidence;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * Store a normalised measurement exactly once.
 *
 * The payload is canonicalised before hashing so the same measurement always
 * produces the same digest, whatever order the source emitted its keys in.
 */
class EvidenceIngestor
{
    public function __construct(
        protected EvidenceHasher $hasher,
    ) {}

    public function ingest(
        EvidenceSource $source,
        EvidenceJob $job,
        NormalisedEvidence $evidence,
    ): EvidenceRecord {
        if ((int) $job->source_id !== (int) $source->getKey()) {
            throw new InvalidArgumentException('The job does not belong to the given evidence source.');
        }

        $payload = $this->hasher->canonicalize($evidence->payload);

        $collectedAt = $evidence->collectedAt->utc();
        $key = hash('sha256', implode('|', [
            (string) $source->getKey(),
            $evidence->externalId,
            $collectedAt->toIso8601ZuluString(),
        ]));

        $attributes = [
            'source_id' => $source->getKey(),
            'job_id' => $job->getKey(),
            'external_id' => $evidence->externalId,
            'asset_id' => $this->resolveAssetId($evidence->assetIdentifier),
            'control_id' => $this->resolveControlId($evidence->controlIdentifier),
            'evidence_type' => $evidence->evidenceType,
            'status' => $evidence->status,
            'collected_at' => $collectedAt,
            'payload' => $payload,
            'payload_sha256' => $this->hasher->hash($payload),
            'ingested_at' => now(),
            'created_at' => now(),
        ];

        try {
            return EvidenceRecord::firstOrCreate(
                ['idempotency_key' => $key],
                $attributes,
            );
        } catch (QueryException $exception) {
            // Two workers can race to insert the same measurement. The unique
            // index is the authority; return the row the winner wrote.
            $existing = EvidenceRecord::query()
                ->where('idempotency_key', $key)
                ->first();

            if ($existing instanceof EvidenceRecord) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function canonicalize(array $payload): array
    {
        return $this->hasher->canonicalize($payload);
    }

    protected function resolveAssetId(?string $identifier): ?int
    {
        if ($identifier === null || trim($identifier) === '') {
            return null;
        }

        $asset = Asset::query()
            ->where('name', $identifier)
            ->orWhere('hostname', $identifier)
            ->orWhere('asset_tag', $identifier)
            ->first();

        return $asset?->getKey();
    }

    protected function resolveControlId(?string $identifier): ?int
    {
        if ($identifier === null || trim($identifier) === '') {
            return null;
        }

        $control = Control::query()
            ->where('code', $identifier)
            ->first();

        return $control?->getKey();
    }
}
