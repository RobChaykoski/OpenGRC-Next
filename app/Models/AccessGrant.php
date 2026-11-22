<?php

namespace App\Models;

use App\Enums\AccessGrantState;
use App\Enums\PrivacyReviewOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A just-in-time access grant (ADR 0049).
 *
 * The grant is the entitlement: who may see what, why, from when, until when,
 * and who approved it. Break-glass grants add a clinical reason and a privacy
 * review obligation with a due date.
 */
class AccessGrant extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'subject_label',
        'protected_resource_id',
        'access_policy_id',
        'requested_by',
        'approved_by',
        'purpose_code',
        'justification',
        'is_break_glass',
        'clinical_reason',
        'state',
        'correlation_id',
        'requested_at',
        'starts_at',
        'expires_at',
        'approved_at',
        'rejected_at',
        'revoked_at',
        'revoked_by',
        'revoked_reason',
        'review_required',
        'review_due_at',
        'reviewed_by',
        'reviewed_at',
        'review_outcome',
        'review_notes',
        'risk_id',
    ];

    protected $casts = [
        'state' => AccessGrantState::class,
        'review_outcome' => PrivacyReviewOutcome::class,
        'is_break_glass' => 'boolean',
        'review_required' => 'boolean',
        'requested_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'review_due_at' => 'date',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    /**
     * @return BelongsTo<ProtectedResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ProtectedResource::class, 'protected_resource_id');
    }

    /**
     * @return BelongsTo<AccessPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(AccessPolicy::class, 'access_policy_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

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
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Risk, $this>
     */
    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    /**
     * @return HasMany<AccessDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(AccessDecision::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', [
            AccessGrantState::Requested->value,
            AccessGrantState::Approved->value,
            AccessGrantState::Active->value,
        ]);
    }

    public function scopeExpirable(Builder $query): Builder
    {
        return $query->whereIn('state', [
            AccessGrantState::Approved->value,
            AccessGrantState::Active->value,
        ])->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    public function scopeReviewOverdue(Builder $query): Builder
    {
        return $query->where('review_required', true)
            ->where('review_outcome', PrivacyReviewOutcome::Pending->value)
            ->whereNotNull('review_due_at')
            ->whereDate('review_due_at', '<', today());
    }

    public function isActive(): bool
    {
        return $this->state === AccessGrantState::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function remainingMinutes(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInMinutes($this->expires_at, false));
    }

    public function reviewIsOverdue(): bool
    {
        return $this->review_required
            && $this->review_outcome->isOpen()
            && $this->review_due_at !== null
            && $this->review_due_at->isPast();
    }

    public function reference(): string
    {
        return 'AG-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['state', 'expires_at', 'approved_by', 'review_outcome'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
