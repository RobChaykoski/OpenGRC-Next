<?php

namespace App\Models;

use App\Enums\ControlAttestationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A request for the control owner to confirm an implementation is still true.
 *
 * The statement is the owner's answer. The record is a dated snapshot; a later
 * change is a new request, not an edit to the old statement.
 */
class ControlAttestation extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'implementation_id',
        'requested_by',
        'requested_at',
        'due_at',
        'status',
        'request_note',
        'attested_by',
        'attested_at',
        'statement',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'due_at' => 'date',
        'status' => ControlAttestationStatus::class,
        'attested_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Implementation, $this>
     */
    public function implementation(): BelongsTo
    {
        return $this->belongsTo(Implementation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attested_by');
    }

    public function reference(): string
    {
        return 'ATT-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isOverdue(): bool
    {
        return $this->status === ControlAttestationStatus::Pending
            && $this->due_at->lt(today());
    }

    /**
     * @param  Builder<ControlAttestation>  $query
     * @return Builder<ControlAttestation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ControlAttestationStatus::Pending->value);
    }

    /**
     * @param  Builder<ControlAttestation>  $query
     * @return Builder<ControlAttestation>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', ControlAttestationStatus::Pending->value)
            ->whereDate('due_at', '<', today());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'due_at', 'attested_by', 'attested_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
