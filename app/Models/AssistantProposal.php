<?php

namespace App\Models;

use App\Enums\AssistantProposalState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A typed change set the assistant proposed and a human decided on (ADR 0055).
 *
 * The operations are data: an operation name, a target id, the value captured at
 * proposal time and the value to set. The model cannot emit SQL or a method
 * name, and an operation outside the catalogue never reaches the table.
 */
class AssistantProposal extends Model
{
    use LogsActivity;

    protected $fillable = [
        'run_id',
        'summary',
        'rationale',
        'operations',
        'state',
        'approved_by',
        'approved_at',
        'applied_by',
        'applied_at',
        'rejected_by',
        'rejected_at',
        'rejected_reason',
        'rolled_back_by',
        'rolled_back_at',
        'rollback_reason',
        'error',
    ];

    protected $casts = [
        'operations' => 'array',
        'state' => AssistantProposalState::class,
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'rejected_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rollbacker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }

    public function operationCount(): int
    {
        return count($this->operations ?? []);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['state', 'approved_by', 'applied_at', 'rolled_back_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
