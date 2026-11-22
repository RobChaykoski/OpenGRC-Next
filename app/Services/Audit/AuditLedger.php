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
            'properties_sha256' => $this->propertiesHash($activity),
            'occurred_at' => $activity->created_at ?? now(),
        ]);

        $entry->previous_hash = $previous;
        $entry->entry_hash = $entry->calculateEntryHash($previous);
        $entry->save();

        return $entry;
    }

    public function propertiesHash(Activity $activity): string
    {
        $properties = $activity->properties?->toArray() ?? [];
        $this->sort($properties);

        return hash('sha256', json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
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
                || $this->propertiesHash($activity) !== $entry->properties_sha256) {
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
