<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EvidenceLedgerEntry extends Model
{
    public const EVENT_SUBMITTED = 'submitted';

    public const EVENT_SUPERSEDED = 'superseded';

    public const EVENT_WITHDRAWN = 'withdrawn';

    public const EVENT_VERIFIED = 'verified';

    public $timestamps = false;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'sequence',
        'event',
        'actor_type',
        'actor_id',
        'reason',
        'file_sha256',
        'file_path',
        'previous_hash',
        'entry_hash',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'sequence' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Evidence ledger entries are append-only.'));
        static::deleting(fn () => throw new \LogicException('Evidence ledger entries are append-only.'));
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
