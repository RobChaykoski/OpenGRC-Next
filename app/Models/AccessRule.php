<?php

namespace App\Models;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Support\Access\AccessRuleValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A platform-wide IP or email-domain allow/deny rule (ADR 0047).
 *
 * Deny wins. An allow rule in a dimension (IP or email domain) turns that
 * dimension into an allowlist; a request that does not match is denied. The
 * dimensions are independent.
 *
 * @property int $id
 * @property AccessRuleType $type
 * @property AccessRuleEffect $effect
 * @property AccessRuleScope $scope
 * @property string $value
 * @property string|null $note
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property-read User|null $creator
 */
class AccessRule extends Model
{
    use HasFactory, LogsActivity;

    public const CACHE_PREFIX = 'access_rules:';

    protected $fillable = [
        'type',
        'effect',
        'scope',
        'value',
        'note',
        'is_active',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'type' => AccessRuleType::class,
        'effect' => AccessRuleEffect::class,
        'scope' => AccessRuleScope::class,
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AccessRule $rule): void {
            $rule->value = AccessRuleValue::normalize($rule->type, (string) $rule->value);

            if ($rule->created_by === null && auth()->check()) {
                $rule->created_by = auth()->id();
            }
        });

        static::saved(fn (AccessRule $rule) => $rule->forgetCache());
        static::deleted(fn (AccessRule $rule) => $rule->forgetCache());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActiveNow(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function matchesIp(?string $ip): bool
    {
        return $this->type === AccessRuleType::Ip
            && $ip !== null
            && $ip !== ''
            && AccessRuleValue::matchesIp($ip, $this->value);
    }

    public function matchesEmail(?string $email): bool
    {
        return $this->type === AccessRuleType::EmailDomain
            && $email !== null
            && $email !== ''
            && AccessRuleValue::matchesEmailDomain($email, $this->value);
    }

    /**
     * @param  Builder<AccessRule>  $query
     * @return Builder<AccessRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @param  Builder<AccessRule>  $query
     * @return Builder<AccessRule>
     */
    public function scopeForScope(Builder $query, AccessRuleScope|string $scope): Builder
    {
        return $query->where('scope', $scope instanceof AccessRuleScope ? $scope->value : $scope);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_PREFIX.$this->scope->value);
        Cache::forget(self::CACHE_PREFIX.'all');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'effect', 'scope', 'value', 'is_active', 'expires_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
