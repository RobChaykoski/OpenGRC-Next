<?php

namespace App\Models;

use App\Enums\DlpPolicyAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A DLP policy as a governance record (ADR 0053).
 *
 * The scanning engine owns detectors and enforcement. This row records the
 * intent a human approved: channels, data classes, identifier types, the action
 * on a match, the owner and the review date. It is not executable and it does
 * not sit in a data path.
 */
class DlpPolicy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'channels',
        'classifications',
        'identifier_types',
        'action',
        'owner_id',
        'review_due_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'channels' => 'array',
        'classifications' => 'array',
        'identifier_types' => 'array',
        'action' => DlpPolicyAction::class,
        'review_due_at' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<DlpFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(DlpFinding::class);
    }

    /**
     * @return HasMany<DlpSuppression, $this>
     */
    public function suppressions(): HasMany
    {
        return $this->hasMany(DlpSuppression::class);
    }

    /**
     * @param  Builder<DlpPolicy>  $query
     * @return Builder<DlpPolicy>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isReviewOverdue(): bool
    {
        return $this->review_due_at !== null && $this->review_due_at->isPast();
    }

    /**
     * A finding is covered when it is linked to this policy or matches its
     * identifier vocabulary. The explicit link wins; the vocabulary is a
     * fallback for producers that do not set one.
     *
     * @param  array<int, string>  $identifierTypes
     */
    public function coversIdentifierTypes(array $identifierTypes): bool
    {
        $configured = $this->identifier_types ?? [];

        if ($configured === []) {
            return true;
        }

        return array_intersect($configured, $identifierTypes) !== [];
    }

    public function reference(): string
    {
        return 'DP-'.str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }
}
