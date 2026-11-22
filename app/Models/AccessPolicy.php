<?php

namespace App\Models;

use App\Enums\AccessAction;
use App\Enums\AccessorProfile;
use App\Enums\ContextTrust;
use App\Enums\DocumentClassification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A policy-based access rule (ADR 0049).
 *
 * tiers x profiles x conditions -> action. A policy is data, not code, so a
 * reviewer can see the rule that fired. The rule set is hashed and the version
 * is bumped when a rule field changes, so a historical decision stays
 * reproducible.
 */
class AccessPolicy extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'tiers',
        'profiles',
        'conditions',
        'action',
        'mask_columns',
        'max_duration_minutes',
        'requires_justification',
        'requires_review',
        'priority',
        'version',
        'rules_hash',
        'is_active',
        'dry_run',
        'owner_id',
        'effective_from',
        'review_due_at',
        'notes',
    ];

    protected $casts = [
        'tiers' => 'array',
        'profiles' => 'array',
        'conditions' => 'array',
        'mask_columns' => 'array',
        'action' => AccessAction::class,
        'max_duration_minutes' => 'integer',
        'requires_justification' => 'boolean',
        'requires_review' => 'boolean',
        'priority' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'dry_run' => 'boolean',
        'effective_from' => 'date',
        'review_due_at' => 'date',
    ];

    /**
     * Fields that change the outcome of a decision. A change to any of them
     * creates a new policy version.
     *
     * @var array<int, string>
     */
    public const RULE_FIELDS = [
        'tiers',
        'profiles',
        'conditions',
        'action',
        'mask_columns',
        'max_duration_minutes',
        'requires_justification',
        'requires_review',
        'priority',
        'dry_run',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $policy): void {
            if ($policy->exists && $policy->isDirty(self::RULE_FIELDS)) {
                $policy->version = (int) $policy->version + 1;
            }

            $policy->rules_hash = $policy->calculateRulesHash();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'action', 'priority', 'version', 'is_active', 'dry_run'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<AccessGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', today());
            });
    }

    public function matchesTier(DocumentClassification $tier): bool
    {
        $tiers = $this->tiers ?? [];

        if ($tiers === []) {
            return true;
        }

        return in_array($tier->value, $tiers, true);
    }

    public function matchesProfile(AccessorProfile $profile): bool
    {
        $profiles = $this->profiles ?? [];

        if ($profiles === []) {
            return true;
        }

        return in_array($profile->value, $profiles, true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function conditionsMet(array $context): bool
    {
        $conditions = $this->conditions ?? [];

        $trust = ContextTrust::tryFrom((string) ($context['trust'] ?? 'low')) ?? ContextTrust::Low;

        if (isset($conditions['min_trust'])) {
            $minimum = ContextTrust::tryFrom((string) $conditions['min_trust']);

            if ($minimum === null || ! $trust->atLeast($minimum)) {
                return false;
            }
        }

        if (($conditions['requires_managed_device'] ?? false) && ! ($context['managed_device'] ?? false)) {
            return false;
        }

        if (($conditions['requires_justification'] ?? false) && ! ($context['has_justification'] ?? false)) {
            return false;
        }

        if (isset($conditions['zones']) && $conditions['zones'] !== []) {
            if (! in_array((string) ($context['zone'] ?? ''), $conditions['zones'], true)) {
                return false;
            }
        }

        if (isset($conditions['purposes']) && $conditions['purposes'] !== []) {
            if (! in_array((string) ($context['purpose_code'] ?? ''), $conditions['purposes'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * How specific the match is. A policy that names tiers and profiles beats a
     * policy that names neither at the same priority.
     */
    public function specificity(): int
    {
        $score = 0;

        if (($this->tiers ?? []) !== []) {
            $score += 2;
        }

        if (($this->profiles ?? []) !== []) {
            $score += 2;
        }

        $score += count($this->conditions ?? []);

        return $score;
    }

    public function calculateRulesHash(): string
    {
        $payload = [
            'code' => $this->code,
            'version' => $this->version,
            'tiers' => $this->sorted($this->tiers ?? []),
            'profiles' => $this->sorted($this->profiles ?? []),
            'conditions' => $this->sortedAssoc($this->conditions ?? []),
            'action' => $this->action?->value,
            'mask_columns' => $this->sorted($this->mask_columns ?? []),
            'max_duration_minutes' => $this->max_duration_minutes,
            'requires_justification' => (bool) $this->requires_justification,
            'requires_review' => (bool) $this->requires_review,
            'priority' => (int) $this->priority,
            'dry_run' => (bool) $this->dry_run,
            'is_active' => (bool) $this->is_active,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function isReviewOverdue(): bool
    {
        return $this->review_due_at !== null && $this->review_due_at->isPast();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    protected function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function sortedAssoc(array $values): array
    {
        ksort($values);

        return $values;
    }
}
