<?php

namespace App\Models;

use App\Enums\DlpSuppressionState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A time-boxed acceptance of a detector's output (ADR 0053).
 *
 * The suppression is scoped to one policy plus, optionally, one identifier type
 * and one match label. It never deletes or hides the finding: the finding stays
 * with its suppression visible, and the suppression expires.
 */
class DlpSuppression extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'dlp_policy_id',
        'identifier_type',
        'match_type',
        'justification',
        'requested_by',
        'approved_by',
        'state',
        'starts_at',
        'expires_at',
        'approved_at',
        'rejected_at',
        'revoked_at',
        'revoked_by',
        'revoked_reason',
        'review_notes',
    ];

    protected $casts = [
        'state' => DlpSuppressionState::class,
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<DlpPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(DlpPolicy::class, 'dlp_policy_id');
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
     * @param  Builder<DlpSuppression>  $query
     * @return Builder<DlpSuppression>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', [
            DlpSuppressionState::Requested->value,
            DlpSuppressionState::Active->value,
        ]);
    }

    /**
     * Suppressions in force right now: approved, started and not expired.
     *
     * @param  Builder<DlpSuppression>  $query
     * @return Builder<DlpSuppression>
     */
    public function scopeInForce(Builder $query): Builder
    {
        return $query->where('state', DlpSuppressionState::Active->value)
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<DlpSuppression>  $query
     * @return Builder<DlpSuppression>
     */
    public function scopeExpirable(Builder $query): Builder
    {
        return $query->where('state', DlpSuppressionState::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    public function isActive(): bool
    {
        return $this->state === DlpSuppressionState::Active
            && ($this->starts_at === null || $this->starts_at->isPast())
            && $this->expires_at->isFuture();
    }

    /**
     * Does this suppression cover the finding? Policy, then the optional
     * identifier type and match label. A suppression with no scope beyond the
     * policy covers every finding that policy raises.
     */
    public function appliesTo(DlpFinding $finding): bool
    {
        if ((int) $finding->dlp_policy_id !== (int) $this->dlp_policy_id) {
            return false;
        }

        if ($this->identifier_type !== null
            && ! in_array($this->identifier_type, $finding->identifier_types ?? [], true)) {
            return false;
        }

        if ($this->match_type !== null
            && ! in_array($this->match_type, $finding->match_types ?? [], true)) {
            return false;
        }

        return true;
    }

    public function reference(): string
    {
        return 'DS-'.str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['state', 'identifier_type', 'match_type', 'expires_at', 'approved_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
