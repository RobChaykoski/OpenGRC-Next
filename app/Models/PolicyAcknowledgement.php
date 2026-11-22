<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A user's acknowledgement of a specific policy revision.
 *
 * A click is not proof of comprehension; it is proof that the policy was
 * presented and accepted. Pair high-risk policies with training or a quiz.
 */
class PolicyAcknowledgement extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'policy_id',
        'user_id',
        'policy_revision',
        'policy_updated_at',
        'acknowledged_at',
        'method',
        'notes',
    ];

    protected $casts = [
        'policy_updated_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * The content revision a user is acknowledging.
     */
    public static function revisionFor(Policy $policy): string
    {
        return hash('sha256', implode('|', [
            (string) $policy->code,
            (string) $policy->name,
            $policy->document_type?->value ?? '',
            (string) $policy->purpose,
            (string) $policy->policy_scope,
            (string) $policy->body,
            $policy->effective_date?->toDateString() ?? '',
        ]));
    }

    /**
     * @return BelongsTo<Policy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrent(): bool
    {
        return $this->policy instanceof Policy
            && $this->policy_revision === self::revisionFor($this->policy);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['policy_id', 'policy_revision', 'acknowledged_at', 'method'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
