<?php

namespace App\Models;

use App\Enums\ControlStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A normalised, immutable measurement (ADR 0009).
 *
 * Corrections are new records with a later collected_at that point at the row
 * they replace. UPDATE and DELETE are blocked in the model and, where the
 * database supports it, by a trigger.
 */
class EvidenceRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source_id',
        'job_id',
        'external_id',
        'client_id',
        'asset_id',
        'control_id',
        'evidence_type',
        'status',
        'collected_at',
        'payload',
        'payload_sha256',
        'idempotency_key',
        'supersedes_id',
        'ingested_at',
        'created_at',
    ];

    protected $casts = [
        'status' => ControlStatus::class,
        'payload' => 'array',
        'collected_at' => 'datetime',
        'ingested_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Evidence records are append-only.'));
        static::deleting(fn () => throw new LogicException('Evidence records are append-only.'));
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EvidenceSource::class, 'source_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(EvidenceJob::class, 'job_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * @return BelongsTo<Control, $this>
     */
    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class, 'control_id');
    }

    /**
     * The record this one replaces, if it is a correction.
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ControlValidation::class, 'evidence_record_id');
    }
}
