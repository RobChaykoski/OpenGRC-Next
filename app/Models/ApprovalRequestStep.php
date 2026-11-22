<?php

namespace App\Models;

use App\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One ordered approval step.
 *
 * Only the first pending step is actionable. A rejection ends the chain; later
 * steps are skipped rather than left pending.
 */
class ApprovalRequestStep extends Model
{
    use LogsActivity;

    protected $fillable = [
        'approval_request_id',
        'sequence',
        'approver_id',
        'status',
        'decided_at',
        'decided_by',
        'comment',
    ];

    protected $casts = [
        'status' => ApprovalStepStatus::class,
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ApprovalRequestStep $step): void {
            if ($step->sequence !== null) {
                return;
            }

            $step->sequence = ((int) static::query()
                ->where('approval_request_id', $step->approval_request_id)
                ->max('sequence')) + 1;
        });
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isCurrent(): bool
    {
        return $this->status === ApprovalStepStatus::Pending
            && $this->request?->currentStep()?->is($this) === true;
    }

    /**
     * @param  Builder<ApprovalRequestStep>  $query
     * @return Builder<ApprovalRequestStep>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStepStatus::Pending->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approver_id', 'decided_by', 'decided_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
