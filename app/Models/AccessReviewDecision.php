<?php

namespace App\Models;

use App\Enums\AccessReviewDecision as AccessDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account in an access review campaign.
 *
 * The role and MFA state are snapshotted at the start of the review so a later
 * role change does not rewrite the evidence.
 */
class AccessReviewDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_review_id',
        'user_id',
        'role_at_review',
        'mfa_at_review',
        'decision',
        'decided_by',
        'decided_at',
        'notes',
    ];

    protected $casts = [
        'mfa_at_review' => 'boolean',
        'decision' => AccessDecision::class,
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AccessReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(AccessReview::class, 'access_review_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->decision === AccessDecision::Pending;
    }
}
