<?php

namespace App\Models;

use App\Enums\EvidenceJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One collection attempt against a source. Operational history, not evidence.
 */
class EvidenceJob extends Model
{
    protected $fillable = [
        'source_id',
        'status',
        'started_at',
        'finished_at',
        'records_seen',
        'records_created',
        'records_duplicate',
        'records_failed',
        'error',
    ];

    protected $casts = [
        'status' => EvidenceJobStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'records_seen' => 'integer',
        'records_created' => 'integer',
        'records_duplicate' => 'integer',
        'records_failed' => 'integer',
    ];

    /**
     * @return BelongsTo<EvidenceSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(EvidenceSource::class, 'source_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(EvidenceRecord::class, 'job_id');
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => EvidenceJobStatus::Running,
            'started_at' => $this->started_at ?? now(),
        ])->save();
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            'status' => EvidenceJobStatus::Completed,
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => EvidenceJobStatus::Failed,
            'finished_at' => now(),
            'error' => $error,
        ])->save();
    }
}
