<?php

namespace App\Services\Audit;

use App\Models\AuditLedgerEntry;
use Spatie\Activitylog\Models\Activity;

/**
 * Mirror activity events into an append-only hash chain (ADR 0056).
 *
 * The activity log is the operational detail and remains editable. This ledger
 * proves the sequence happened: each entry hashes the previous one, and the
 * properties are represented by a digest rather than a second copy of the
 * payload. Verification recomputes the chain and re-hashes the live activity row,
 * so editing the activity table is visible.
 */
class AuditLedger
{
    /**
     * Version 2 hashes `attribute_changes` and `properties`; version 1 hashed
     * `properties` only. Entries keep the version they were written with.
     */
    public const HASH_VERSION = 2;

    public function append(Activity $activity): AuditLedgerEntry
    {
        $previous = AuditLedgerEntry::query()->orderByDesc('id')->value('entry_hash');

        $entry = new AuditLedgerEntry([
            'activity_id' => $activity->getKey(),
            'log_name' => $activity->log_name,
            'event' => (string) ($activity->event ?? 'log'),
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'actor_type' => $activity->causer_type,
            'actor_id' => $activity->causer_id,
            'description' => $activity->description,
            'properties_sha256' => $this->digestForVersion($activity, self::HASH_VERSION),
            'hash_version' => self::HASH_VERSION,
            'occurred_at' => $activity->created_at ?? now(),
        ]);

        $entry->previous_hash = $previous;
        $entry->entry_hash = $entry->calculateEntryHash($previous);
        $entry->save();

        return $entry;
    }

    public function digestForVersion(Activity $activity, int $version): string
    {
        if ($version < 2) {
            return $this->hashPayload($activity->properties?->toArray() ?? []);
        }

        return $this->hashPayload([
            'attributes' => $activity->attribute_changes?->toArray() ?? [],
            'properties' => $activity->properties?->toArray() ?? [],
        ]);
    }

    /**
     * @return array{total: int, broken: int|null, altered: int, missing: int}
     */
    public function verify(): array
    {
        $previous = null;
        $broken = null;
        $altered = 0;
        $missing = 0;
        $total = 0;

        foreach (AuditLedgerEntry::query()->orderBy('id')->cursor() as $entry) {
            $total++;

            if ($entry->previous_hash !== $previous || $entry->calculateEntryHash($previous) !== $entry->entry_hash) {
                $broken = (int) $entry->getKey();

                break;
            }

            $previous = $entry->entry_hash;
            $activity = $entry->activity;

            if ($activity === null) {
                $missing++;

                continue;
            }

            if ((string) ($activity->description ?? '') !== (string) $entry->description
                || $this->digestForVersion($activity, (int) $entry->hash_version) !== $entry->properties_sha256) {
                $altered++;
            }
        }

        return [
            'total' => $total,
            'broken' => $broken,
            'altered' => $altered,
            'missing' => $missing,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hashPayload(array $payload): string
    {
        $this->sort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sort(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sort($value);
            }
        }
    }
}
