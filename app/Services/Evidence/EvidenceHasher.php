<?php

namespace App\Services\Evidence;

use App\Models\EvidenceRecord;

/**
 * Canonical hashing for evidence payloads.
 *
 * The digest is computed from a key-sorted payload so the same measurement
 * produces the same hash regardless of key order. The inspector recomputes it
 * to prove the stored payload still matches the recorded digest.
 */
class EvidenceHasher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function canonicalize(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->canonicalize($value);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hash(array $payload): string
    {
        $json = json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return hash('sha256', $json);
    }

    public function matches(EvidenceRecord $record): bool
    {
        $payload = is_array($record->payload) ? $record->payload : [];

        return hash_equals((string) $record->payload_sha256, $this->hash($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function prettyJson(array $payload): string
    {
        return json_encode(
            $this->canonicalize($payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '{}';
    }
}
