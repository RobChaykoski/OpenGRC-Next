<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * One link in the audit ledger (ADR 0056).
 *
 * A mirrored activity event: what happened, to what, by whom, when, and a hash of
 * the properties — never the properties themselves. The row is append-only at the
 * model layer and the database has UPDATE and DELETE triggers.
 */
class AuditLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'activity_id',
        'log_name',
        'event',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'description',
        'properties_sha256',
        'hash_version',
        'previous_hash',
        'entry_hash',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'hash_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->created_at ??= now();
        });

        static::updating(function (): void {
            throw new LogicException('The audit ledger is append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('The audit ledger is append-only and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function calculateEntryHash(?string $previousHash): string
    {
        $payload = [
            'log_name' => $this->log_name,
            'event' => (string) $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'description' => $this->description,
            'properties_sha256' => (string) $this->properties_sha256,
            'occurred_at' => $this->occurred_at?->toIso8601ZuluString(),
            'previous_hash' => $previousHash,
        ];

        // Version 1 hashed `properties`; version 2 also hashes `attribute_changes`.
        // Appending the version keeps the old hashes verifiable.
        if ((int) $this->hash_version >= 2) {
            $payload['hash_version'] = (int) $this->hash_version;
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }
}
