<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A named streaming producer inside a client (ADR 0054).
 *
 * The secret signs the request; it is encrypted at rest and shown once. The row
 * also holds the acceptance and rejection counters, because a rejected body is
 * never stored and the operator still has to see that it happened.
 */
class EvidenceStream extends Model
{
    use LogsActivity;

    protected $fillable = [
        'client_id',
        'name',
        'secret',
        'is_active',
        'last_cursor',
        'last_received_at',
        'accepted_count',
        'rejected_count',
        'last_rejection_reason',
        'last_rejection_at',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'is_active' => 'boolean',
        'last_received_at' => 'datetime',
        'last_rejection_at' => 'datetime',
        'accepted_count' => 'integer',
        'rejected_count' => 'integer',
    ];

    /**
     * @param  Builder<EvidenceStream>  $query
     * @return Builder<EvidenceStream>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function markAccepted(string $cursor): void
    {
        $this->forceFill([
            'last_cursor' => $cursor,
            'last_received_at' => now(),
            'accepted_count' => $this->accepted_count + 1,
        ])->save();
    }

    public function markRejected(string $reason): void
    {
        $this->forceFill([
            'rejected_count' => $this->rejected_count + 1,
            'last_rejection_reason' => mb_substr($reason, 0, 255),
            'last_rejection_at' => now(),
        ])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['client_id', 'name', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
