<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A multi-step approval request.
 *
 * The request is a register record. It is not attached to a specific policy,
 * risk or vendor yet; the title and description say what is being approved.
 */
class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'requested_by',
        'due_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => ApprovalRequestStatus::class,
        'due_at' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<ApprovalRequestStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class)->orderBy('sequence');
    }

    public function currentStep(): ?ApprovalRequestStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->first(fn (ApprovalRequestStep $step): bool => $step->status === ApprovalStepStatus::Pending);
        }

        return $this->steps()->where('status', ApprovalStepStatus::Pending->value)->first();
    }

    public function reference(): string
    {
        return 'APR-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalRequestStatus::Pending;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at !== null && $this->due_at->lt(today());
    }
}
