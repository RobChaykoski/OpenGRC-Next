<?php

namespace App\Models;

use App\Enums\AccessReviewDecision as DecisionType;
use App\Enums\AccessReviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A periodic user access review campaign.
 *
 * Starting a review snapshots every account and its role. The reviewer attests
 * or revokes each account; the campaign is complete only when none are pending.
 */
class AccessReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'status',
        'reviewer_id',
        'created_by',
        'due_at',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'status' => AccessReviewStatus::class,
        'due_at' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AccessReviewDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(AccessReviewDecision::class);
    }

    public function pendingCount(): int
    {
        return $this->decisions()->where('decision', DecisionType::Pending->value)->count();
    }

    public function isActive(): bool
    {
        return $this->status === AccessReviewStatus::Active;
    }

    public function reference(): string
    {
        return 'AR-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }
}
